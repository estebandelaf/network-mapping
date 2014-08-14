#!/usr/bin/php
<?php

/**
 * Map BGP Dump
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
 * Map BGP Dump
 *
 * Herramienta para creación de un mapa de interconexión de sistemas autónomos
 * BGP utilizando los caminos entre un sistema autónomo local y redes remotas.
 *
 * Modo de uso:
 *   $ ./map_bgp_dump.php ARCHIVO_JSON|DIRECTORIO [NOMBRE_ARCHIVOS_GENERADOS]
 *
 * Requiere:
 *   - dot (paquete: graphviz)
 *
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-08-14
 */

// string para el gráfico dot
define('DOT_LGS_AS', "\t".'"%d" [label="%d", style="filled", color="black", fillcolor="yellow"];'."\n");
define('DOT_AS_TO_AS', "\t".'"%d" -- "%d" [color=red];'."\n");

// función para mostrar el modo de uso
function modo_de_uso()
{
    global $argv;
    echo '[error] modo de uso: ',$argv[0],' ARCHIVO_JSON|DIRECTORIO [NOMBRE_ARCHIVOS_GENERADOS]',"\n";
    exit(1);
}

// verificar que se hayan pasado dos parámetros
if (!isset($argv[1])) {
    modo_de_uso();
}

// definir archivos JSON que se usarán
if (is_dir($argv[1])) {
    $files = [];
    $aux = scandir($argv[1]);
    foreach ($aux as &$f) {
        if (substr($f, -4)=='json') {
            $partes = explode('.', $f);
            $files[$partes[0]] = $argv[1].DIRECTORY_SEPARATOR.$f;
        }
    }
}
else if (is_file($argv[1])) {
    $partes = explode('.', basename($argv[1]));
    $files = [$partes[0] => $argv[1]];
}
else {
    modo_de_uso();
}

// definir nombres de archivos
if (!isset($argv[2])) {
    $dot_file = 'map_bgp_dump.dot';
    $png_file = 'map_bgp_dump.png';
} else {
    $dot_file = $argv[2].'.dot';
    $png_file = $argv[2].'.png';
}

// cargar tablas de rutas desde el/los archivo(s) JSON
$data = [];
foreach($files as $name => &$f) {
    $data[$name] = json_decode(file_get_contents($f));
}

// generar mapa
file_put_contents($dot_file, bgp_generate_map($data));
system('unflatten -l3 '.$dot_file.' | dot -Tpng -o '.$png_file);

/**
 * Función que genera el código dot para el mapa a partir de las rutas BGP
 * @param data Arreglo con múltiples tabla de rutas (con los caminos de los ASs)
 * @return Código dot del grafo generado para el mapa
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-08-14
 */
function bgp_generate_map($data)
{
    $conexiones = [];
    // iniciar código dot
    ob_start();
    echo 'strict graph topology {',"\n";
    // generar conexiones para cada uno de los LGS entregados
    foreach ($data as $local_as => &$rutas) {
        // agregar LGS
        echo sprintf (DOT_LGS_AS, $local_as, $local_as);
        // generar grafo con la topología
        foreach ($rutas as $network => &$as_paths) {
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
    }
    // terminar código dot y entregar
    echo '}',"\n";
    $dot = ob_get_contents();
    ob_end_clean();
    return $dot;
}
