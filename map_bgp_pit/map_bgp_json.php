#!/usr/bin/php
<?php

/**
 * Map BGP JSON
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
 * Map BGP JSON
 *
 * Herramienta para creación de un mapa de interconexión de sistemas autónomos
 * BGP utilizando los caminos entre un sistema autónomo local y redes remotas.
 *
 * Modo de uso:
 *   $ ./map_bgp_snmp.php LOCAL_AS JSON_FILE
 *
 * Requiere:
 *   - dot (paquete: graphviz)
 *
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-06-24
 */

// string para el gráfico dot
define('DOT_LGS_AS', "\t".'%d [label="%d", style="filled", color="black", fillcolor="orange"];'."\n");
define('DOT_REMOTE_AS', "\t".'%d [label="%d", style="filled", color="black", fillcolor="yellow"];'."\n");
define('DOT_AS_TO_AS', "\t".'"%d" -- "%d" [color=red];'."\n");

// verificar que se hayan pasado dos parámetros
if (!isset($argv[2])) {
    echo '[error] modo de uso: ',$argv[0],' LOCAL_AS JSON_FILE',"\n";
    exit(1);
}

// definir nombres de archivos si se indicó un solo host
$local_as = $argv[1];
$dot_file = 'AS'.$local_as.'.dot';
$png_file = 'AS'.$local_as.'.png';

// cargar tabla de rutas desde el archivo JSON
$data = json_decode(file_get_contents($argv[2]));

// generar mapa
file_put_contents($dot_file, bgp_generate_map($local_as, $data));
system('unflatten -l3 '.$dot_file.' | dot -Tpng -o '.$png_file);

/**
 * Función que genera el código dot para el mapa a partir de AS y rutas BGP
 * @param local_as Sistema autónomo local del LGS
 * @param data Tabla de rutas (con los caminos de los ASs)
 * @return Código dot del grafo generado para el mapa
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-06-24
 */
function bgp_generate_map($local_as, $data)
{
    $conexiones = [];
    // iniciar código dot
    ob_start();
    echo 'strict graph topology {',"\n";
    // generar coneciones para cada tabla de rutas entregada
    echo sprintf (DOT_LGS_AS, $local_as, $local_as);
    // generar grafo con la topología
    foreach ($data as $network => &$as_paths) {
        // agregar cada uno de los caminos entre AS
        foreach ($as_paths as &$as_path) {
            // agregar conexiones entre AS
            if (!in_array([$local_as, $as_path[0]], $conexiones) && !in_array([$as_path[0], $local_as], $conexiones)) {
                echo sprintf (DOT_AS_TO_AS, $local_as, $as_path[0]);
                $conexiones[] = [$local_as, $as_path[0]];
            }
            $n_as = count($as_path);
            for ($i=0; $i<$n_as-1; $i++) {
                if (!in_array([$as_path[$i], $as_path[$i+1]], $conexiones) && !in_array([$as_path[$i+1], $as_path[$i]], $conexiones)) {
                    echo sprintf (DOT_AS_TO_AS, $as_path[$i], $as_path[$i+1]);
                    $conexiones[] = [$as_path[$i], $as_path[$i+1]];
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
