# CyberPlan 🛡️
### Sistema de Control de Cronogramas de Ciberseguridad
**AUNOR / Aleatica — Red Vial 4**

---

## Estructura del Proyecto

```
cyberplan/
├── index.php           ← Dashboard principal (UI completa)
├── config.php          ← Configuración BD y constantes
├── schema.sql          ← Esquema MySQL con datos iniciales
├── api/
│   └── cronograma.php  ← API REST (GET/POST/PUT/DELETE)
└── README.md
```

---

## Requisitos

- **PHP 8.0+** con extensión PDO y pdo_mysql
- **MySQL 8.0+** o MariaDB 10.6+
- Servidor web: Apache, Nginx, o `php -S localhost:8000`

---

## Instalación

### 1. Base de datos

```bash
mysql -u root -p < schema.sql
```

O desde phpMyAdmin: importar `schema.sql`.

### 2. Configurar conexión

Editar `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cyberplan');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');
```

### 3. Servidor de desarrollo

```bash
cd cyberplan/
php -S localhost:8000
# Abrir http://localhost:8000
```

### 4. En producción (Apache)

Colocar la carpeta en `htdocs/` o `www/` y acceder por la URL de tu servidor.

---

## Funcionalidades

### Dashboard
- 📊 Tarjetas de estadísticas: total actividades, programadas, ejecutadas, vencidas
- 📈 Gráfico de barras: cumplimiento mensual P vs E
- 🎯 Gauge de cumplimiento general con porcentaje animado
- 📋 Tabla de actividades del mes actual

### Cronograma
- 📅 Vista matricial: actividades × 12 meses
- 🟦 **P** (azul) = Programado | 🟩 **E** (verde) = Ejecutado
- 🔴 P vencido (mes pasado sin E) resaltado en rojo
- ✅ Toggle con un clic para marcar P o E en cualquier celda
- 🔍 Filtros por categoría (F1/F2/F3), responsable, búsqueda de texto
- 📥 Exportación CSV

### Gestión de Actividades
- ➕ Crear nuevas actividades
- ✏️ Editar actividades existentes
- 🗑️ Eliminar actividades (soft delete)

---

## API REST

**Base URL:** `api/cronograma.php`

| Método | Acción | Descripción |
|--------|--------|-------------|
| GET | `?action=cronograma&anio=2025` | Cronograma completo |
| GET | `?action=stats&anio=2025` | Estadísticas dashboard |
| GET | `?action=actividades&anio=2025` | Lista de actividades |
| POST | `?action=toggle` | Activar/desactivar P o E en celda |
| POST | `?action=actividad` | Crear actividad |
| PUT | `?action=actividad` | Actualizar actividad |
| DELETE | `?action=actividad&id=5` | Eliminar actividad |

---

## Paleta de colores (Aleatica Brand)

| Color | Hex | Uso |
|-------|-----|-----|
| Verde | `#72BF44` | Ejecutado, éxito, primario |
| Naranja | `#F99B1C` | Alertas, pendiente |
| Azul | `#00BBE7` | Programado, información |

**Tipografía:** Titillium Web (fuente oficial Aleatica)

---

## Notas

- Las categorías **F2** y **F3** corresponden a los documentos del sistema de ciberseguridad de AUNOR (NIST CSF 2.0)
- El mes actual se resalta automáticamente en el cronograma
- Los P de meses pasados sin E correspondiente se marcan como **vencidos** (rojo)
- Los datos de programación se almacenan en la tabla `programacion` con clave única `(actividad_id, anio, mes, tipo)`
