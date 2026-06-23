# AGENTS.md

## Proyecto

Este proyecto se llama DropaivSys2.

Es un sistema Laravel avanzado para gestión de abastecimiento, cotizaciones, estudios de mercado, clientes, proveedores, artículos y documentos.

Este proyecto NO es PeruAsoCebu.
Este proyecto NO es un sistema ganadero.
No usar instrucciones del proyecto PeruAsoCebu.

## Estado actual

El sistema ya está avanzado y tiene una base funcional con:
- Laravel
- Autenticación
- Usuarios
- Roles y permisos
- AdminLTE
- Dashboard administrativo
- Módulos maestros
- Vistas Blade
- JavaScript por módulos
- Rutas organizadas
- Seeders
- Migraciones existentes

No crear otro login.
No reemplazar AdminLTE.
No rehacer el proyecto desde cero.
No eliminar módulos existentes sin autorización.

## Objetivo del sistema

Continuar el desarrollo de DropaivSys2 respetando lo ya construido.

El sistema está orientado a:
- Clientes
- Proveedores
- Artículos
- Marcas
- Presentaciones
- Unidades
- Categorías y subcategorías
- Bancos
- Estudios de mercado
- Cotizaciones de proveedores
- Cotizaciones finales al cliente
- Documentos adjuntos
- Reportes

## Reglas obligatorias

- Antes de modificar, revisar la estructura actual.
- Antes de crear un módulo nuevo, verificar si ya existe algo parecido.
- No duplicar controladores, modelos, rutas, migraciones ni vistas.
- No cambiar nombres de tablas existentes sin autorización.
- No borrar migraciones antiguas.
- No modificar autenticación sin autorización.
- No modificar roles/permisos sin explicar el impacto.
- No romper rutas existentes.
- No cambiar el layout principal sin autorización.
- Mantener textos visibles en español.
- Usar nombres técnicos en inglés para modelos, tablas y columnas.
- Respetar el estilo visual actual del sistema.
- Usar AdminLTE, Bootstrap, jQuery y DataTables si el proyecto ya los usa.
- Si hay JavaScript modular por archivo, seguir esa misma convención.

## Flujo de trabajo

Cuando se pida una tarea:
1. Analizar archivos relacionados.
2. Explicar brevemente el plan.
3. Modificar solo lo necesario.
4. Indicar archivos modificados.
5. Indicar comandos necesarios.
6. No ejecutar migraciones destructivas sin autorización.

## Comandos útiles

composer install
npm install
npm run dev
npm run build
php artisan migrate
php artisan optimize:clear
php artisan route:list
php artisan test

## Reglas de seguridad

No subir ni modificar datos sensibles.
No exponer claves del archivo .env.
No subir vendor ni node_modules.
No cambiar configuración de producción sin autorización.

## Instrucción principal para Codex

Trabaja únicamente sobre DropaivSys2.
Si encuentras referencias de otro proyecto, no las uses como base sin confirmación.
Este proyecto debe mantenerse como sistema de abastecimiento/cotizaciones, no como sistema ganadero.