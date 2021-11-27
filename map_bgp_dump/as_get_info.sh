#!/bin/sh

#
# Script para generar archivo CSV con datos de los AS
# Copyright (C) 2014 Esteban De La Fuente Rubio, DeLaF (esteban[at]delaf.cl)
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

whois_ipduh () {
    curl --data "IPin=$1" "http://ipduh.com/ip/whois/as/" 2> /dev/null \
        | xmllint --html -xpath 'string(id("hm")/tr[4]/td/pre)' - 2> /dev/null
}

whois_lacnic () {
    curl --data "query=$1" "http://lacnic.net/cgi-bin/lacnic/whois?lg=EN" 2> /dev/null \
        | xmllint --html -xpath 'string(/html/body/div/div[2]/h4/pre)' - 2> /dev/null
}

# función para obtener los datos de un sistema autónomo desde whois
bgp_as_get_info_whois () {
    TMP="/tmp/as_info_`tr -dc A-Za-z0-9_ < /dev/urandom | head -c 10 | xargs`"
    whois_lacnic $1 | egrep -v -e '^ #|^ %|^[[:space:]]*$' > $TMP
    recode ISO-8859-15..UTF8 $TMP
    OWNER=$(whois_get_data "`egrep -i '^ owner:|^ OrgName:|^ descr:' $TMP | head -1`")
    COUNTRY=$(whois_get_data "`egrep -i '^ Country:' $TMP | head -1`")
    echo -n "$AS;$OWNER;$COUNTRY"
    rm -f $TMP
}

# función para obtener los datos de un sistema autónomo
# asignación: http://www.iana.org/assignments/as-numbers/as-numbers.xml
bgp_as_get_info () {
    NO_COUNTRY="NN"
    if [ $AS -ge 64000 -a $AS -le 64495 ]; then
        echo -n "$AS;IANA;$NO_COUNTRY"
    else
        if [ $AS -ge 64496 -a $AS -le 64511 -o $AS -ge 65536 -a $AS -le 65551 ]; then
            echo -n "$AS;RFC5398;$NO_COUNTRY"
        else
            if [ $AS -ge 64512 -a $AS -le 65534 ]; then
                echo -n "$AS;RFC6996;$NO_COUNTRY"
            else
                if [ $AS -eq 0 -o $AS -ge 65552 -a $AS -le 131071 ]; then
                    echo -n "$AS;Reserved;$NO_COUNTRY"
                else
                    if [ $AS -ge 4200000000 -a $AS -le 4294967294 ]; then
                        echo -n "$AS;RFC6996;$NO_COUNTRY"
                    else
                        if [ $AS -eq 65535 -o $AS -eq 4294967295 ]; then
                            echo -n "$AS;RFC7300;$NO_COUNTRY"
                        else
                            if [ $AS -ge 133632 -a $AS -le 196607 -o $AS -ge 202240 -a $AS -le 262143 -o $AS -ge 263680 -a $AS -le 327679 -o $AS -ge 328704 -a $AS -le 393215 -o $AS -ge 394240 -a $AS -le 4199999999 ]; then
                                echo -n "$AS;Unallocated;$NO_COUNTRY"
                            else
                                echo -n `bgp_as_get_info $AS`
                            fi
                        fi
                    fi
                fi
            fi
        fi
    fi
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
                echo $INFO >> $CSV
            # si existe se reemplaza
            else
                sed -i "s|$EXISTE|$INFO|g" $CSV
            fi
            # a pesar que lo manda a un archivo, mostrar igual por pantalla
            echo $INFO
        fi
    else
        INFO=`bgp_as_get_info $AS`
        echo $INFO
    fi
done
