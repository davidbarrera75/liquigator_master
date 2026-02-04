---
name: deploy-liq
description: Despliega Liquigator a producción. SOLO usar cuando los cambios estén probados en local.
disable-model-invocation: true
argument-hint: "[mensaje del deploy]"
---

# Deploy de Liquigator a Producción

## Servidor de Producción
- **IP:** 72.61.36.170
- **Usuario:** root
- **Ruta:** /var/www/liquigator

## Pre-requisitos
Antes de desplegar, verifica:
1. ✅ Los cambios funcionan en local (http://localhost:8888)
2. ✅ Los cambios están commiteados y pusheados a GitHub
3. ✅ No hay errores en la consola del navegador
4. ✅ La cache local está limpia

## Pasos del Deploy

### 1. Verificar estado de Git local
```bash
git status
git log --oneline -3
```

### 2. Conectar al servidor y hacer pull
```bash
ssh root@72.61.36.170
cd /var/www/liquigator
git pull origin main
```

### 3. Limpiar cache en producción
```bash
php bin/console cache:clear --env=prod
```

### 4. Verificar permisos
```bash
chown -R www-data:www-data /var/www/liquigator/var
```

### 5. Verificar que funciona
- Abrir https://liquigator.12y3.online
- Probar las funcionalidades modificadas

## Mensaje del Deploy
$ARGUMENTS

## IMPORTANTE
- **NUNCA** hacer cambios directamente en producción
- Si algo falla, revertir con `git checkout` o restaurar backup
- Los backups están en `/var/www/liquigator/backups/`
