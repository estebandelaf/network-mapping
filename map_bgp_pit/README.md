Generación de mapa usando PIT
=============================

PIT: "Corresponde, para los efectos de la medición de los indicadores de calidad 
a que se refiere esta norma, al punto de intercambio de tráfico nacional de 
internet, que cumple la función de agrupar e intercambiar el tráfico de dos o 
más ISPs." <http://www.pitentel.cl>

Se utiliza el PIT pit.nap.cl para la obtención de los sistemas autónomos de 
redes en Chile. Luego usando el archivo JSON generado se crea el grafo con dot.

Modo de uso
-----------

Primero obtener rutas de sistemas autónomos:

	$ ./mrlg_get_as.sh networks_cl > AS19411.json

Donde *networks_cl* es el archivo con las redes asignadas a proveedores en 
Chile.

Luego generar el mapa con:

	$ ./map_bgp_snmp.php 19411 AS19411.json

Donde *19411* es el sistema autónomo del PIT al que nos conectamos y 
*AS19411.json* es el archivo generado al obtener las rutas.
