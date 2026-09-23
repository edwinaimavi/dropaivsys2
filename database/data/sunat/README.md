# Datos de Catálogos SUNAT

Los archivos oficiales validados se incorporarán en `catalogs/`, con un archivo JSON por tabla
SUNAT (por ejemplo, `catalogs/12.json`). El seeder no contiene listas extensas en PHP y solo lee
archivos `*.json` ubicados directamente en esa carpeta.

Los lotes documentales incorporados contienen los catálogos `01`, `02`, `03`, `05`, `06`, `10`,
`11`, `12`, `13` y `14`, con 294 elementos validados contra la información entregada. Los demás
catálogos permanecen pendientes y no deben incorporarse hasta completar su validación documental.

Reglas principales:

- `catalog_code` y cada `items[].code` siempre son cadenas para preservar ceros a la izquierda.
- `source` usa `sunat_pdf` para la fuente documental entregada.
- `extra_data` admite columnas variables propias de cada tabla SUNAT.
- `description` admite hasta 2000 caracteres para preservar literalmente textos documentales extensos.
- El proceso es idempotente y no elimina registros que no estén presentes en los archivos.
- El esquema formal está en `schema.json`.
