# Resumen de Semanas Cotizadas - Documentacion Tecnica

## Descripcion General

Cuando se sube un PDF de **Colpensiones**, el sistema extrae DOS secciones de datos:

1. **DETALLE DE PAGOS EFECTUADOS** (existente) - Registros mensuales con periodo + IBC. Se guardan en la tabla `data`.
2. **RESUMEN DE SEMANAS COTIZADAS POR EMPLEADOR** (nuevo, Feb 2026) - Lista de empleadores con rango de fechas, salario, semanas y totales. Se guarda en la tabla `resumen_semanas`.

---

## Flujo de Datos

```
PDF Colpensiones
    |
    v
bin/extract_pdf.py (Python3 + pdfplumber)
    |
    +-- extraer_colpensiones()       --> data[] (periodos + IBC)
    +-- extraer_colpensiones_resumen() --> resumen_semanas[] (empleadores)
    |
    v
JSON de salida:
{
  "success": true,
  "data": [...],              // detalle mensual
  "semanas": 1582.14,
  "resumen_semanas": [...]    // resumen por empleador
}
    |
    v
PdfPlumberService.php::transformToMainControllerFormat()
    |  Pasa resumen_semanas tal cual al resultado
    v
MainController.php::datos()
    |
    +-- Loop data[] --> crea entidades Data (existente)
    +-- Loop resumen_semanas[] --> crea entidades ResumenSemanas (nuevo)
    |
    v
MySQL: tablas `data` + `resumen_semanas`
    |
    v
MainController.php::resumenSemanas()
    |  Ruta: /main/{uniqid}/resumen-semanas
    v
templates/main/client/resumen-semanas.html.twig
    |  Tabla Bootstrap con totales
    v
Navegador (menu sidebar: "Resumen Semanas Cotizadas")
```

---

## Estructura de la Tabla PDF (Colpensiones)

### RESUMEN DE SEMANAS COTIZADAS (paginas 1-14 aprox.)

Encabezado del PDF:
```
[1]Identificacion Aportante | [2]Nombre o Razon Social | [3]Desde | [4]Hasta | [5]Ultimo Salario | [6]Semanas | [7]Lic | [8]Sim | [9]Total
```

Ejemplo de fila:
```
1008219597 | JOM METODOS INTERNAL | 20/12/1985 | 01/07/1986 | $21.420 | 27,71 | 0,00 | 0,00 | 27,71
```

### DETALLE DE PAGOS EFECTUADOS (paginas 16+ aprox.)

Encabezado del PDF:
```
[34]ID Aportante | [35]Nombre | [36]RA | [37]Periodo | [38]Fecha Pago | [39]Ref Pago | [40]IBC | [41]Cotizacion | ... | [46]Observacion
```

Ejemplo de fila:
```
890307478 | FERRETERIA DEL VALLE | SI | 199501 | 13/02/1995 | 19001502000086 | $ 59.475 | $ 7.434 | ...
```

### Como se diferencian ambas tablas en el extractor

La funcion `extraer_colpensiones_resumen()` valida:
- **Columna [2] (Desde)**: debe tener formato `DD/MM/YYYY` (regex: `^\d{2}/\d{2}/\d{4}$`)
- **Columna [5] (Semanas)**: debe ser numero decimal (regex: `^\d+[.,]\d+$`)

Esto descarta las filas del DETALLE DE PAGOS que tienen "SI"/"NO" en columna [2] y codigos de referencia en columna [5].

---

## Archivos Involucrados

| Archivo | Funcion |
|---------|---------|
| `bin/extract_pdf.py` | `extraer_colpensiones_resumen()` - extraccion Python con pdfplumber |
| `src/Entity/ResumenSemanas.php` | Entidad Doctrine (tabla `resumen_semanas`) |
| `src/Entity/Information.php` | Relacion OneToMany hacia ResumenSemanas |
| `src/Service/PdfPlumberService.php` | Pasa `resumen_semanas` del JSON Python al controller |
| `src/Controller/MainController.php` | `datos()` guarda ResumenSemanas; `resumenSemanas()` nueva ruta |
| `templates/main/client/resumen-semanas.html.twig` | Vista con tabla Bootstrap |
| `templates/base.html.twig` | Menu sidebar con enlace |
| `migrations/Version20260220090000.php` | Migracion CREATE TABLE |

---

## Entidad ResumenSemanas

```php
// src/Entity/ResumenSemanas.php
// Tabla: resumen_semanas

id                   INT (PK, auto)
nombre_razon_social  VARCHAR(500)   -- Nombre del empleador
desde                VARCHAR(20)    -- Fecha inicio (DD/MM/YYYY)
hasta                VARCHAR(20)    -- Fecha fin (DD/MM/YYYY)
ultimo_salario       VARCHAR(50)    -- Salario tal cual del PDF (ej: "$21.420")
semanas              VARCHAR(20)    -- Semanas cotizadas (ej: "27,71")
sim                  VARCHAR(20)    -- Semanas simultaneas (ej: "0,00")
total                VARCHAR(20)    -- Total semanas (ej: "27,71")
info_id              INT (FK)       -- Relacion con information.id (CASCADE DELETE)
```

**Nota:** Todos los campos son strings porque se guardan tal cual vienen del PDF, sin transformacion numerica. Los totales se calculan en el template Twig al renderizar.

---

## Consideraciones para Otros Fondos

Solo Colpensiones tiene la seccion de "Resumen de Semanas Cotizadas". Los demas fondos (Porvenir, Colfondos, Skandia, Proteccion) no se ven afectados:
- La funcion `extraer_colpensiones_resumen()` solo se ejecuta si `fondo == "colpensiones"` en `extraer_pdf()`.
- En `MainController::datos()`, el bloque que crea ResumenSemanas solo se ejecuta si `$extract['resumen_semanas']` existe.
- El menu sidebar siempre muestra el enlace (para todos los fondos), pero la pagina muestra un mensaje informativo si no hay datos.

---

## Fallback cuando Python no esta disponible

Si el extractor pdfplumber falla (Python no instalado, error de lectura), el sistema cae al fallback:
1. **Claude AI** (si esta configurado)
2. **ExtractService legacy** (PHP)

Estos fallbacks **NO extraen resumen_semanas** - solo extraen el detalle de pagos. Por eso, si la extraccion se hizo con fallback, la pagina de resumen mostrara "Sin datos de resumen".

**Requisito:** Python3 + pdfplumber deben estar instalados en el contenedor Docker (ver Dockerfile).

---

## Deploy a Produccion

Al desplegar en el VPS (72.61.36.170):

1. Subir los archivos modificados
2. Ejecutar la migracion:
   ```bash
   cd /var/www/liquigator
   php bin/console doctrine:migrations:migrate --no-interaction
   ```
3. Verificar que Python3 + pdfplumber estan instalados en el servidor:
   ```bash
   python3 -c "import pdfplumber; print('OK')"
   ```
   Si no estan:
   ```bash
   apt-get install -y python3 python3-pip
   pip3 install pdfplumber
   ```
4. Limpiar cache:
   ```bash
   php bin/console cache:clear --env=prod
   ```
