# Actualización de Datos 2026 - Liquigator

**Fecha de actualización**: 2026-01-12
**Problema resuelto**: Error 500 en módulo Proyección

---

## Problema Identificado

El módulo de **Proyección** estaba generando un error 500 cuando se intentaba acceder.

### Error en logs:
```
[2026-01-08T19:17:42] request.CRITICAL: Uncaught PHP Exception Exception:
"No se encontró salario mínimo para el año 2026. Por favor, configure este valor en la base de datos."
at /var/www/liquigator/src/Service/IpcService.php line 54
```

### Causa raíz:
- Faltaba el registro de **salario mínimo** para el año 2026
- El valor de **IPC** para 2026 estaba incorrecto (porcentaje: 2.2, ipc: 3.2)

---

## Solución Aplicada

### 1. Salario Mínimo 2026
Se agregó/actualizó el registro en la tabla `salario_minimo`:

```sql
INSERT INTO salario_minimo (anio, valor, tope)
VALUES (2026, 1750905, 43772625);
```

**Valores:**
- Año: 2026
- Valor: $1,750,905
- Tope: $43,772,625 (25 veces el salario mínimo)

### 2. IPC 2026
Se corrigió el registro en la tabla `ipc`:

```sql
UPDATE ipc
SET porcentaje = 0.022000, ipc = 1.022000
WHERE anio = 2026;
```

**Valores corregidos:**
- Porcentaje: 0.022000 (representa 2.2%)
- IPC (factor): 1.022000 (multiplicador usado en cálculos)

### 3. Limpieza de caché
```bash
cd /var/www/liquigator
php bin/console cache:clear
chown -R www-data:www-data var/cache/
```

---

## Formato de Datos en Liquigator

### Tabla: `salario_minimo`
| Campo | Descripción | Ejemplo 2026 |
|-------|-------------|--------------|
| anio  | Año | 2026 |
| valor | Salario mínimo mensual | 1750905 |
| tope  | Tope (25 veces salario) | 43772625 |

### Tabla: `ipc`
| Campo | Descripción | Ejemplo 2026 (2.2%) |
|-------|-------------|---------------------|
| anio  | Año | 2026 |
| porcentaje | IPC% ÷ 100 | 0.022000 |
| ipc   | 1 + porcentaje | 1.022000 |

**Conversión de IPC:**
- Si IPC es X%, entonces:
  - `porcentaje = X / 100`
  - `ipc = 1 + (X / 100)`
- Ejemplo con 2.2%:
  - `porcentaje = 2.2 / 100 = 0.022`
  - `ipc = 1 + 0.022 = 1.022`

---

## Cómo Diagnosticar Errores Similares

### 1. Revisar logs
```bash
tail -100 /var/www/liquigator/var/log/prod.log
```

### 2. Verificar datos en base de datos
```bash
# Ver salarios mínimos registrados
mysql -u root -p[PASSWORD] liquigator -e "SELECT * FROM salario_minimo ORDER BY anio DESC LIMIT 5;"

# Ver IPC registrados
mysql -u root -p[PASSWORD] liquigator -e "SELECT * FROM ipc ORDER BY anio DESC LIMIT 5;"
```

### 3. Archivo con contraseña de BD
```bash
cat /root/liquigator_db_password.txt
```
Contraseña actual: `eo31yeLE+d5Z38K9`

---

## Valores de Referencia 2024-2026

### Salarios Mínimos
| Año  | Valor       | Tope        |
|------|-------------|-------------|
| 2024 | 1,300,000   | 32,500,000  |
| 2025 | 1,423,500.9 | 35,587,522.5|
| 2026 | 1,750,905   | 43,772,625  |

### IPC
| Año  | Porcentaje | IPC     | % Real |
|------|------------|---------|--------|
| 2024 | 0.092800   | 1.09280 | 9.28%  |
| 2025 | 0.052000   | 1.05200 | 5.2%   |
| 2026 | 0.022000   | 1.02200 | 2.2%   |

---

## Módulos que Dependen de Estos Datos

1. **Proyección** - Calcula proyecciones futuras usando IPC y salarios
2. **ClaudeService** - Chat AI que usa estos datos en respuestas
3. **Reportes** - Generación de informes con ajustes por IPC
4. **Cotizaciones** - Actualización de valores según IPC

---

## Checklist para Actualización Anual

Cuando llegue un nuevo año (2027, 2028, etc.), realizar:

- [ ] Agregar salario mínimo del nuevo año en tabla `salario_minimo`
- [ ] Agregar IPC del nuevo año en tabla `ipc` (verificar formato)
- [ ] Limpiar caché de Symfony
- [ ] Probar módulo de Proyección
- [ ] Verificar que reportes funcionen correctamente
- [ ] Actualizar este documento con los nuevos valores

---

## Comandos Útiles

```bash
# Conectar al servidor
ssh root@72.61.36.170

# Ir al directorio de la aplicación
cd /var/www/liquigator

# Ver logs en tiempo real
tail -f var/log/prod.log

# Limpiar caché
php bin/console cache:clear && chown -R www-data:www-data var/cache/

# Acceder a MySQL
mysql -u root -p$(cat /root/liquigator_db_password.txt) liquigator
```

---

**Última actualización por**: Claude Code
**Próxima actualización estimada**: Enero 2027 (agregar valores para 2027)
