#!/bin/sh

#
# Script para generar archivo CSV con datos de los AS
# Copyright (C) 2014 Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
#
# El script hace uso http://ipduh.com/ip/whois/as/
#

# si no se pasó un parámetro -> error
if [ $# -eq 0 ]; then
    echo "[error] modo de uso: $0 ARCHIVO_AS [ARCHIVO_CSV]"
    exit 1
fi

# definir sistemas autónomos que se consultarán
if [ -f "$1" ]; then
    ASs="`cat $1`"
else
    ASs="$1"
fi

# si se pasó un segundo parámetro será el archivo donde guardar los datos
if [ $# -eq 2 ]; then
    CSV=$2
    if [ ! -f $CSV ]; then
        touch $CSV
    fi
else
    CSV=""
fi

# función para extraer los datos desde la info de whois
whois_get_data () {
    echo $1 | awk '{
        gsub("\r", "\n");
        for(i=2;i<=NF;i++) {
            printf "%s", $i;
            if(i<NF)
                printf " "
        }
    }'
}

# función para obtener información de un sistema autónomo
bgp_as_get_info () {
    TMP="/tmp/as_info_`tr -dc A-Za-z0-9_ < /dev/urandom | head -c 10 | xargs`"
    curl --data "IPin=$1" "http://ipduh.com/ip/whois/as/" 2> /dev/null \
        | xmllint --html -xpath 'string(id("hm")/tr[4]/td/pre)' - 2> /dev/null \
        | egrep -v -e '^ #|^ %|^[[:space:]]*$' > $TMP
    recode ISO-8859-15..UTF8 $TMP
    # obtener propietario
    OWNER=$(whois_get_data "`egrep -i "^ owner:|^ OrgName:|^ descr:" $TMP | head -1`")
    # obtener país
    COUNTRY=$(whois_get_data "`egrep -i "^ Country:" $TMP | head -1`")
    echo -n "$OWNER;$COUNTRY"
    rm -f $TMP
}

# procesar cada uno de los sistemas autónomos e ir obteniendo sus datos
for AS in $ASs; do
    # si se pasó un archivo CSV se completa el archivo, sin sobreescribir datos
    # que puedan existir (datos incompletos se sobreescribirán)
    if [ "$CSV" != "" ]; then
        # si ya hay datos en el archivo para el sistema autónomo no se procesa
        EXISTE=`egrep "^$AS;" $CSV`
        # solo procesar si no existe el registro en el archivo o bien está vacio
        if [ "$EXISTE" = "" -o "$EXISTE" = "$AS;;" ]; then
            INFO=`bgp_as_get_info $AS`
            # si no existe se agrega
            if [ "$EXISTE" = "" ]; then
                echo "$AS;$INFO" >> $CSV
            # si existe se reemplaza
            else
                sed -i 's|$AS;;|$AS;$INFO|g' $CSV
            fi
            # a pesar que lo manda a un archivo, mostrar igual por pantalla
            echo "$AS;$INFO"
        fi
    else
        INFO=`bgp_as_get_info $AS`
        echo "$AS;$INFO"
    fi
done
