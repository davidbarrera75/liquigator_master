---
name: liq
description: Atajo rápido para ajustes en Liquigator. Usa /liq [descripción] para hacer cambios rápidos.
argument-hint: "[qué ajustar]"
---

# Ajuste Rápido de Liquigator

Estás trabajando en **Liquigator** (~/liquigator).

## Contexto Rápido
- **Stack:** Symfony 5.1 + PHP 8.3 + MySQL 8.0
- **Local:** http://localhost:8888
- **Docker:** `docker-compose up -d`

## Archivos Principales
- Lógica: `src/Controller/MainController.php`
- Vistas: `templates/main/`
- Servicios: `src/Service/`
- Entidades: `src/Entity/`

## Tu Tarea
$ARGUMENTS

## Pasos
1. Lee el archivo antes de modificar
2. Haz el cambio solicitado
3. Limpia cache: `docker exec liquigator_app php bin/console cache:clear`
4. Verifica en http://localhost:8888
