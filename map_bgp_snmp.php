#!/usr/bin/php
<?php

/**
 * Map BGP SNMP
 * Copyright (C) 2014 Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU publicada
 * por la Fundación para el Software Libre, ya sea la versión 3
 * de la Licencia, o (a su elección) cualquier versión posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General GNU para obtener
 * una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/gpl.html>.
 *
 */

/**
 * Map BGP SNMP
 *
 * Herramienta para creación de un mapa de red utilizando las tablas de rutas
 * entregadas por el protocolo BGP a través de SNMP. Se puede construir el mapa
 * a partir de los datos recolectados de solo un looking glass server o bien de
 * varios (mezclando mapas).
 *
 * Se hace uso del OID: 1.3.6.1.2.1.15 (iso.org.dod.internet.mgmt.mib-2.bgp)
 *
 * Modo de uso:
 *   $ ./map_bgp_snmp.php HOST1|FILE [HOST2, ...]
 *
 * Requiere:
 *   - Módulo SNMP para php (paquete: php5-snmp)
 *   - dot (paquete: graphviz)
 *
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-06-19
 */

// string para el gráfico dot
define('DOT_LGS_AS', "\t".'%d [label="%d", style="filled", color="black", fillcolor="orange"];'."\n");
define('DOT_REMOTE_AS', "\t".'%d [label="%d", style="filled", color="black", fillcolor="yellow"];'."\n");
define('DOT_LOCAL_NETWORK', "\t".'"%d" -- "%s/%d" [color=blue];'."\n");
define('DOT_REMOTE_NETWORK', "\t".'"%d" -- "%s/%d" [color=blue];'."\n");
define('DOT_AS_TO_AS', "\t".'"%d" -- "%d" [color=red];'."\n");

// verificar que exista soporte para SNMP
if (!class_exists('\SNMP')) {
    echo '[error] no hay soporte para SNMP en PHP (falta módulo php5-snmp)';
    exit(1);
}

// verificar que se haya pasado al menos un parámetro
if (!isset($argv[1])) {
    echo '[error] modo de uso: ',$argv[0],' HOST1|FILE [HOST2, ...]',"\n";
    exit(1);
}

// determinar de donde sacar los hosts
if (file_exists($argv[1])) {
    $hosts = explode("\n", trim(file_get_contents($argv[1])));
} else {
    $hosts = array_slice($argv, 1);
}

// definir nombres de archivos si se indicó un solo host
if (!isset($hosts[1])) {
    $local_host = $hosts[0];
    $local_as = snmp_bgp_get_local_as($local_host);
    $dot_file = $local_as.'_'.$local_host.'.dot';
    $png_file = $local_as.'_'.$local_host.'.png';
}
// definir nombres de archivos si se indicaron varios hosts
else {
    date_default_timezone_set('America/Santiago');
    $time = date('U');
    $dot_file = 'network_map_'.$time.'.dot';
    $png_file = 'network_map_'.$time.'.png';
}

// obtener tabla de rutas para cada AS
$data = [];
foreach ($hosts as &$host) {
    $data[snmp_bgp_get_local_as($host)] = snmp_bgp_get_routing_table($host);
}

// generar mapa
file_put_contents($dot_file, bgp_generate_map($data));
system('unflatten -l3 '.$dot_file.' | dot -Tpng -o '.$png_file);

/**
 * Función que obtiene el AS del looking glass server que nos conectamos
 * @param host Equipo del que queremos obtener su sistema autónomo
 * @param community Comunidad SNMP del host
 * @return Sistema autónomo (AS) del host
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-06-19
 */
function snmp_bgp_get_local_as($host, $community = 'public')
{
    $snmp = new \SNMP(\SNMP::VERSION_2C, $host, $community);
    $aux = $snmp->walk('1.3.6.1.2.1.15.2');
    $snmp->close();
    $local_as = (int)substr(array_shift($aux), 9);
    return $local_as;
}

/**
 * Función que obtiene la tabla de rutas BGP del host al que nos conectamos
 * @param host Equipo del que queremos obtener su tabla de rutas
 * @param community Comunidad SNMP del host
 * @return Arreglo con la tabla de rutas que ve el host
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-06-16
 */
function snmp_bgp_get_routing_table($host, $community = 'public')
{
    // crear objeto para las consultas al servidor snmp que tiene las rutas BGP
    $snmp = new \SNMP(\SNMP::VERSION_2C, $host, $community);
    // obtener datos de la tabla de rutas
    $data = [
        'networks' => $snmp->walk('1.3.6.1.2.1.15.6.1.3'),
        'masks' => $snmp->walk('1.3.6.1.2.1.15.6.1.2'),
        'next_hops' => $snmp->walk('1.3.6.1.2.1.15.6.1.6'),
        'as_paths' => $snmp->walk('1.3.6.1.2.1.15.6.1.5')
    ];
    $snmp->close();
    unset($snmp);
    // procesar datos obtenidos mediante snmp y generar tabla de rutas
    $rt = [];
    while ($data['networks']) {
        $network = array_shift($data['networks']);
        $mask = array_shift($data['masks']);
        $next_hop = array_shift($data['next_hops']);
        $as_path_aux = array_shift($data['as_paths']);
        $as_path_aux = substr($as_path_aux, 18);
        $as_path = [];
        while($as_path_aux) {
            $as = substr($as_path_aux, 0, 6);
            $as_path_aux = str_replace($as, '', $as_path_aux);
            $as = str_replace(' ', '', $as);
            $as_path[] = hexdec($as);
        }
        $rt[] = [
            'network' => substr($network, 11),
            'mask' => substr($mask, 9),
            'next_hop' => substr($next_hop, 11),
            'as_path' => $as_path
        ];
    }
    return $rt;
}

/**
 * Función que genera el código dot para el mapa a partir de AS y rutas BGP
 * @param data Arreglo con índice AS y valor tabla de rutas de cada looking glass server
 * @return Código dot del grafo generado para el mapa
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-06-19
 */
function bgp_generate_map($data)
{
    // "trampa" para no marcar AS de LGS como remotos
    $remote_ass = array_keys($data);
    // iniciar código dot
    ob_start();
    echo 'strict graph topology {',"\n";
    // generar coneciones para cada tabla de rutas entregada
    foreach ($data as $local_as => &$rt) {
        echo sprintf (DOT_LGS_AS, $local_as, $local_as);
        foreach ($rt as &$r) {
            foreach($r['as_path'] as &$as) {
                if (!in_array($as, $remote_ass)) {
                    $remote_ass[] = $as;
                    echo sprintf (DOT_REMOTE_AS, $as, $as);
                }
            }
        }
        // generar grafo con la topología
        foreach ($rt as &$r) {
            $n_as = count($r['as_path']);
            // si no hay AS es una red directamente conectada al AS
            if (!$n_as) {
                echo sprintf (DOT_LOCAL_NETWORK, $local_as, $r['network'], $r['mask']);
            }
            // si hay al menos un AS entonces la red estará conectada al último en el PATH
            else {
                echo sprintf (DOT_REMOTE_NETWORK, $r['as_path'][$n_as-1], $r['network'], $r['mask']);
            }
            // si existe solo un AS está conectado al AS local
            if ($n_as==1) {
                echo sprintf (DOT_AS_TO_AS, $local_as, $r['as_path'][0]);
            }
            // si existen al menos 2 AS entonces se debe generar la ruta de AS
            else if ($n_as>1) {
                for ($i=0; $i<$n_as-1; $i++) {
                    echo sprintf (DOT_AS_TO_AS, $r['as_path'][$i], $r['as_path'][$i+1]);
                }
            }
        }
    }
    // terminar código dot y entregar
    echo '}',"\n";
    $dot = ob_get_contents();
    ob_end_clean();
    return $dot;
}
