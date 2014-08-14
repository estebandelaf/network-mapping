#!/bin/sh

#
# Script para generar archivo JSON con el dump de rutas de un router.
# Copyright (C) 2014 Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
#
# Se procesa la salida del comando "show ip bgp" que para la versión usada la
# cabecera tiene 6 líneas y el pie tiene 2 líneas. En caso que la cantidad de
# líneas de cabecera o pie cambie se puede modificar en las variables HEADER y
# FOOTER respectivamente.
#
# Ejemplo de cabecera:
#-------------------------------------------------------------------------------
# BGP table version is 0, local router ID is 200.1.123.20
# Status codes: s suppressed, d damped, h history, * valid, > best, i - internal,
#               r RIB-failure, S Stale, R Removed
# Origin codes: i - IGP, e - EGP, ? - incomplete
#
#    Network          Next Hop            Metric LocPrf Weight Path
#-------------------------------------------------------------------------------
#
# Ejemplo de pie:
#-------------------------------------------------------------------------------
#
# Total number of prefixes 3273
#-------------------------------------------------------------------------------
#

# Cantidad de líneas en la cabecera y el pie
HEADER=6
FOOTER=2

# función para mostrar modo de uso
function modo_de_uso() {
    echo "[error] modo de uso: $0 ARCHIVO|DIRECTORIO"
    exit 1
}

# si no se pasó un parámetro -> error
if [ $# -ne 1 ]; then
    modo_de_uso
fi

# definir archivo(s) a utilizar para extraer rutas
if [ -f "$1" ]; then
    FILES=$1
else
    if [ -d $1 ]; then
        FILES=`ls $1/*.txt`
    else
        modo_de_uso
    fi
fi

# archivo temporal que se usará
TMP="/tmp/map_bgp_dump_`tr -dc A-Za-z0-9_ < /dev/urandom | head -c 10 | xargs`"

# procesar cada archivo
for FILE in $FILES; do
    # determinar desde que línea y hasta que línea están las rutas
    let DESDE=$HEADER+1
    let HASTA=`wc -l $FILE | awk {'print $1'}`-$FOOTER
    # extrar solo líneas de rutas y pegar aquellas que están divididas en dos
    # líneas en una sola
    awk -v DESDE=$DESDE -v HASTA=$HASTA 'NR >= DESDE && NR <= HASTA' $FILE \
        | awk '{
            if(substr($0,0,1)=="*") {
                if(NF>2)
                    print $0;
                else {
                    gsub("\r", "");
                    line = $0;
                }
            } else
                print line " " $0;
        }' \
        > $TMP
    # generar código JSON
    echo "{" > $FILE.json
    RUTAS=`wc -l $TMP | awk {'print $1'}`
    awk -v RUTAS=$RUTAS '
        {
            printf "    \"%s\": [[", $2;
            start_as_field = 0
            for(i=NF-1;i>2;i--) {
                if($i==0) {
                    start_as_field = i + 1
                    break
                }
            }
            for(i=start_as_field;i<NF;++i) {
                printf "%s", $i;
                if(i<NF-1)
                    printf ", ";
            }
            printf "]]";
            if(NR<RUTAS)
                print ",";
            else print "";
        }' \
        $TMP >> $FILE.json
    echo "}" >> $FILE.json
done

# borrar archivo temporal usado
rm -f $TMP
