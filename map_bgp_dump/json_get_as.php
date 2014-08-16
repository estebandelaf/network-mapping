#!/usr/bin/php
<?php

/**
 * json_get_as
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
 * json_get_as
 *
 * Script que extrae los AS desde un(os) archivo(s) JSON
 *
 * Modo de uso:
 *   $ ./json_get_as.php ARCHIVO_JSON|DIRECTORIO [NOMBRE_ARCHIVO_GENERADO]
 *
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-08-15
 */

// función para mostrar el modo de uso
function modo_de_uso()
{
    global $argv;
    echo '[error] modo de uso: ',$argv[0],' ARCHIVO_JSON|DIRECTORIO [NOMBRE_ARCHIVO_GENERADO]',"\n";
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
    $file = 'as_list.txt';
} else {
    $file = $argv[2].'.txt';
}

// cargar tablas de rutas desde el/los archivo(s) JSON
$data = [];
foreach($files as $name => &$f) {
    $data[$name] = json_decode(file_get_contents($f));
}

// generar listado de AS
file_put_contents($file, implode("\n", bgp_get_as($data))."\n");

/**
 * Función que extrae los AS desde los JSON
 * @param data Tablas de rutas de los diferentes LGS
 * @return Arreglo con los AS
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
 * @version 2014-08-14
 */
function bgp_get_as($data)
{
    $conexiones = [];
    // procesar cada LGS
    foreach ($data as $local_as => &$rutas) {
        // procesar cada red
        foreach ($rutas as $network => &$as_paths) {
            // agregar cada uno de los caminos entre AS
            foreach ($as_paths as &$as_path) {
                foreach($as_path as &$as) {
                    if(!in_array($as, $conexiones)) {
                        $conexiones[] = $as;
                    }
                }
            }
        }
    }
    sort($conexiones);
    return $conexiones;
}
