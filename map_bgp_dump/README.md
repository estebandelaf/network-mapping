Generación de mapa usando dump de tabla BGP
===========================================

Se hace uso de un dump completo de la tabla BGP del router, se parsea y se 
extraen las redes y los sistemas autónomos de la ruta hacia dicha red.

Se requiere tener de forma previa el dump de los routers.

Nota respecto al nombre de archivo en los dump
----------------------------------------------

El (o los) archivos *.txt* que contienen el (o los) dump(s) del router DEBE 
respetar el siguiente formato en el nombre:

	AS.ISP.txt

Donde *AS* es el sistema autónomo donde se encuentra el *looking glass server* 
(*LGS*) desde el cual se sacaron las rutas e *ISP* es un string cualquiera que 
identifica al dueño del *LGS*. Ejemplo:

	6471.entel.txt

Lo anterior es necesario ya que se usará el AS que existe en el nombre del 
archivo como el AS del *LGS* para poder identificar a dicho AS con un color 
diferente en el grafo.

Modo de uso
-----------

**Nota**: en estos ejemplos no se uso el formato de nombre antes mencionado, ya 
que se desconocía el AS de cada proveedor. Por lo cual al generar el gráfico 
aparecerá como AS 0 (en un nodo amarillo).

### Generar archivos JSON

Primero se deben *parsear* los dumps y generar los archivos JSON con las tablas 
de rutas BGP para cada red del *LGS*. Esto se puede hacer de dos formas, la 
primera es parseando solo un dump (un archivo *.txt*):

	$ ./generate_json.sh examples/nac/entel.txt

O bien parseando todos los archivos *.txt* de un directorio:

	$ ./generate_json.sh examples/nac/entel.txt

### Generar grafo (código dot y png)

Aquí hay 4 opciones:

-	Generar grafo para un solo archivo JSON sin indicar nombre de archivos.
-	Generar grafo para todos los JSON en un directorio sin indicar el nombre 
	de los archivos.
-	Generar grafo para un solo archivo JSON indicando nombre de archivos.
-	Generar grafo para todos los JSON en un directorio indicando el nombre 
	de los archivos.

Por ejemplo para generar el grafo del archivo JSON de la tabla de rutas 
existentes en *examples/nac/entel.txt.json* y asignar un nombre a los archivos 
se ejecuta:

	$ ./map_bgp_dump.php examples/nac/entel.txt.json map_bgp_dump_entel

En cambio para generar el código dot y png de todos los JSON con el nombre por 
defecto se ejecuta:

	$ ./map_bgp_dump.php examples/nac
