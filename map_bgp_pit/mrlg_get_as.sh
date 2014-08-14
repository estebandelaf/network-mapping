#!/bin/sh

#
# Script para generar archivo JSON con las rutas BGP de un PIT
# Copyright (C) 2014 Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
#
# El script hace uso del PIT pit.nap.cl
#

# verificar que se haya pasado un archivo como argumento
if [ ! -f $1 ]; then
    echo "[error] modo de uso: $0 ARCHIVO_REDES"
    exit 1
fi

# función para obtener los sistemas autónomos
mrlg_get_as () {
    LGS="http://pit.nap.cl/cgi-bin/lg.cgi"
    DATA="router=AS19411 NAP Chile&query=1&arg=$1"
    curl --data "$DATA" $LGS 2> /dev/null \
        | xmllint --html -xpath "/html/body/pre" - 2> /dev/null \
        | egrep ", \(received" | awk -F "," '{print $1}' | awk '!x[$0]++' \
        | sed -e 's/^ *//' -e 's/ *$//'
}

# procesar cada una de las redes e ir generando el archivo JSON
FIRST=1
echo "{"
for IP in `cat $1`; do
    AS=`mrlg_get_as $IP | sed 's/ /, /g' | sed ':a;N;$!ba;s/\n/], [/g' \
        | awk '{printf "%s", "[["$0"]]"}'`
    if [ "$AS" != "" ]; then
        if [ $FIRST -eq 0 ]; then
            echo ","
        fi
        echo -n "    \"$IP\": "
        echo -n $AS
        FIRST=0
    fi
done
echo ""
echo "}"
