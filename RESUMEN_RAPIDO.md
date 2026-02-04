# LIQUIGATOR - Resumen Rápido de Referencia

**Última actualización**: 2026-01-12
**Servidor**: 72.61.36.170 (root)
**Proyecto**: /var/www/liquigator
**URL**: https://liquigator.12y3.online

---

## IMPLEMENTACIÓN ACTUAL: Chat con Claude AI

### Archivos Clave Modificados

1. **`/var/www/liquigator/src/Service/ClaudeService.php`** (CREADO)
   - Línea ~79-120: Construcción de datos de cotización (ENVÍA TODOS LOS DATOS)
   - Función `sendMessage()`: Comunica con API de Claude
   - Función `buildSystemPrompt()`: Construye contexto con datos del usuario

2. **`/var/www/liquigator/src/Controller/MainController.php`**
   - Línea ~1338: Función `chat()` agregada
   - Detecta flag `full_history` para optimizar contexto
   - Optimización: >40 registros → primeros 20 + resumen estadístico + últimos 20

3. **`/var/www/liquigator/templates/main/client.html.twig`**
   - Final del archivo: Panel de chat completo (HTML + CSS + JS)
   - Flag: `full_history: false`

4. **`/var/www/liquigator/templates/main/client/resumen-completo.html.twig`**
   - Final del archivo: Panel de chat completo (HTML + CSS + JS)
   - Flag: `full_history: true`

5. **`/var/www/liquigator/.env.local`** (CREADO)
   - API Key de Claude almacenada

6. **`/var/www/liquigator/config/services.yaml`**
   - Servicio ClaudeService registrado

---

## Problemas Críticos Resueltos

### ✅ Claude no veía datos de años específicos
**Causa**: ClaudeService.php línea 79-85 solo enviaba primeros 10 registros
**Fix**: Modificado para enviar TODOS los datos sin límite

### ✅ Botones de minimizar no visibles
**Causa**: Sin contraste en fondo morado
**Fix**: Agregado `background: rgba(255,255,255,0.25)` + borde

### ✅ Chat bloqueaba vista de datos
**Fix**: Agregada funcionalidad minimizar/maximizar

### ✅ Error 500 en módulo Proyección (2026-01-12)
**Causa**: Falta salario mínimo e IPC incorrecto para año 2026
**Fix**: Agregado salario mínimo $1,750,905 y corregido IPC al 2.2%
**Documentación**: Ver `/var/www/liquigator/ACTUALIZACION_2026.md`

---

## Comandos Esenciales

```bash
# Limpiar cache
cd /var/www/liquigator && php bin/console cache:clear && chown -R www-data:www-data var/cache/

# Ver logs
tail -f /var/www/liquigator/var/log/prod.log

# Conectar al servidor
ssh root@72.61.36.170
```

---

## API de Claude

**Modelo**: claude-3-haiku-20240307
**API Key**: En `/var/www/liquigator/.env.local`
**Endpoint**: https://api.anthropic.com/v1/messages
**Costo**: ~$0.001-$0.003 USD por pregunta

---

## Endpoint del Chat

**Ruta**: `/client/{uniqid}/chat`
**Método**: POST
**Parámetros**:
- `message`: Pregunta del usuario
- `history`: Historial JSON
- `full_history`: true/false

---

## Estados del Chat

1. **Cerrado**: No visible
2. **Abierto**: Panel completo (400px)
3. **Minimizado**: Solo header visible

---

## Funcionalidades Implementadas

- [x] Chat en vista regular (10 años)
- [x] Chat en vista completa (toda la vida)
- [x] Acceso a historia completa optimizado
- [x] Minimizar/maximizar chat
- [x] Claude ve TODOS los datos de cotización
- [x] Optimización de tokens con resumen estadístico
- [x] Datos 2026 actualizados (salario mínimo + IPC)

---

## Documentación Disponible

1. **`/var/www/liquigator/DOCUMENTACION_CHAT_CLAUDE.md`**
   - Tamaño: 18KB
   - Contenido: Detalles completos de implementación del chat, errores resueltos, estructura técnica

2. **`/var/www/liquigator/LIQUIGATOR_REFERENCIA_COMPLETA.md`**
   - Referencia completa del sistema

3. **`/var/www/liquigator/ACTUALIZACION_2026.md`** ⭐ NUEVO
   - Tamaño: 4.3KB
   - Contenido: Solución error 500 módulo Proyección
   - Valores 2026: Salario mínimo ($1,750,905) e IPC (2.2%)
   - Checklist para actualizaciones anuales futuras
   - Comandos útiles para diagnóstico

---

**Para retomar el proyecto:**
- Chat con Claude: Leer `/var/www/liquigator/DOCUMENTACION_CHAT_CLAUDE.md`
- Datos anuales (IPC/Salario): Leer `/var/www/liquigator/ACTUALIZACION_2026.md`
