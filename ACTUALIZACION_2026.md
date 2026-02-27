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

## Actualización 2026-02-27: Proyección con Base "Toda la Vida" + Piso Pensión Mínima

### Problema / Necesidad

1. El módulo de Proyección solo permitía crear escenarios basados en la liquidación de los **últimos 10 años**. El usuario necesitaba también poder proyectar desde la liquidación de **toda la vida laboral** (la que arroja el módulo Resumen de Semanas Cotizadas).

2. Las pensiones calculadas podían dar valores inferiores al salario mínimo vigente, lo cual es ilegal en Colombia.

### Cambios Realizados

#### 1. Proyección con dos bases de cálculo

**Archivos modificados:**
- `src/Controller/MainController.php`
  - Método `proyeccion()` (GET, línea ~527): Ahora calcula la liquidación de toda la vida (`ad_vida`) usando `getInfoListed()` sin LIMIT, y la pasa al template.
  - Método `proyection()` (POST, línea ~1229): Lee nuevo parámetro `base_calculo` del formulario. Si es `toda_la_vida`, usa `getInfoListed()` sin LIMIT. Si es `10_anios` (default), mantiene LIMIT 120. El valor se guarda en `json_data.base_calculo`.

- `templates/main/client/proyeccion.html.twig`
  - **Sidebar (col-sm-4):** Dos tarjetas con la liquidación actual:
    - Tarjeta azul "Últimos 10 Años" — IBL, Pensión Básica, Pensión Total
    - Tarjeta verde "Toda la Vida" — IBL, Pensión Básica, Pensión Total
  - **Modal "Agregar Escenario":** Dos tarjetas seleccionables (radio buttons visuales) que muestran IBL y Pensión Total de cada base. Al hacer clic se resalta la seleccionada.
  - **Tarjetas de escenarios:** Badge azul "10 Años" o verde "Toda la Vida" en el header según la base usada.

**Lógica clave:**
```php
// En proyection() POST:
$baseCalculo = $req['base_calculo'] ?? '10_anios';

if ($baseCalculo === 'toda_la_vida') {
    // Sin LIMIT: toda la vida laboral
    $data = $em->getRepository(Data::class)->getInfoListed($info->getId(), $ipc->getAnio());
} else {
    // LIMIT 120: últimos 10 años
    $data = $em->getRepository(Data::class)->getInfoListed(
        $info->getId(), $ipc->getAnio(),
        $total_meses_proyeccion > 120 ? 120 : (120 - $total_meses_proyeccion)
    );
}
// Se guarda en json_data:
$data['base_calculo'] = $baseCalculo;
```

#### 2. Piso de pensión mínima (Art. 35 Ley 100/1993)

**Regla legal:** En Colombia, ninguna pensión puede ser inferior al salario mínimo legal mensual vigente (SMMLV).

**Implementación:**
- **Pensión Básica (R1):** Siempre muestra el valor real calculado según aportes, sin ajustar. Esto refleja el cálculo actuarial real.
- **Pensión Total (R2):** Si el cálculo da menos que el SMMLV del año en curso (`$this->year`), se ajusta al SMMLV. Se guarda un flag `pension_minima = true` para mostrar la nota legal.

**Archivos modificados en el controller (`MainController.php`):**
- `resumenSemanas()` — línea ~639
- `resumenCompleto()` — línea ~704
- `client()` — línea ~871
- `proyection()` POST — línea ~1367
- `proyeccion()` GET (cálculo `ad_vida`) — línea ~552

**Código aplicado en cada lugar:**
```php
// Art. 35 Ley 100/1993: ninguna pensión puede ser inferior al SMMLV
$smmlv = $this->ipcService->salarioMinimo($this->year);
$ad['pension_minima'] = $ad['R2'] < $smmlv;
$ad['R2'] = max($ad['R2'], $smmlv);
```

**Templates con nota legal (cuando `pension_minima` es true):**
- `templates/main/client.html.twig` — vista Últimos 10 Años
- `templates/main/client/resumen-semanas.html.twig` — vista Resumen Semanas
- `templates/main/client/resumen-completo.html.twig` — vista Toda la Vida
- `templates/main/client/proyeccion.html.twig` — tarjetas y modal de detalles

**Texto de la nota:**
> *Art. 35 Ley 100 de 1993: En ningún caso la pensión podrá ser inferior al salario mínimo legal mensual vigente.*

### Resumen Visual

```
┌─────────────────────────────────────────────────────┐
│ MÓDULO PROYECCIÓN                                    │
│                                                      │
│ ┌─────────────┐  ┌─────────────┐                    │
│ │ Escenario 1 │  │ Escenario 2 │   Sidebar:         │
│ │ [10 Años]   │  │ [Toda Vida] │   ┌──────────────┐ │
│ │             │  │             │   │ AGREGAR       │ │
│ │ IBL: $X     │  │ IBL: $Y     │   │ ESCENARIO    │ │
│ │ R1:  $X     │  │ R1:  $Y     │   ├──────────────┤ │
│ │ R2:  $X     │  │ R2:  $Y     │   │ [10 Años]    │ │
│ │ *Ley 100    │  │             │   │ Pensión: $X  │ │
│ └─────────────┘  └─────────────┘   ├──────────────┤ │
│                                     │ [Toda Vida]  │ │
│ Modal Agregar:                      │ Pensión: $Y  │ │
│ ┌──────────┬──────────┐            └──────────────┘ │
│ │ 10 Años  │Toda Vida │                             │
│ │ R2: $X   │ R2: $Y   │  ← seleccionar base        │
│ └──────────┴──────────┘                             │
└─────────────────────────────────────────────────────┘
```

---

**Última actualización por**: Claude Code
**Última modificación**: 2026-02-27
**Próxima actualización estimada**: Enero 2027 (agregar valores para 2027)
