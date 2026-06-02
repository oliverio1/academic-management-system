# Módulo: Cartera Vencida (aislado del núcleo académico)

## Objetivo
Implementar un módulo financiero para control de adeudos y pagos de alumnos, sin dependencia funcional de clases, asistencias, sesiones, actividades ni calificaciones.

---

## Principios de aislamiento

1. **Bounded context independiente**  
   Todo el módulo vive bajo namespace propio:
   - `App\\Models\\Finance\\*`
   - `App\\Http\\Controllers\\Finance\\*`
   - `resources/views/finance/*`
   - rutas con prefijo `finance` o `coordination/finance`.

2. **Sin hooks académicos**  
   No se modifica lógica de:
   - `Attendance*`
   - `Grade*`
   - `AcademicSession*`
   - `Teacher*` (planeación/evaluación)

3. **Solo referencia de alumno/campus/ciclo**  
   El módulo solo usa IDs para contexto:
   - `student_id`
   - `campus_id`
   - `school_cycle_id`

4. **Transacciones contables inmutables**  
   No borrar pagos ni cargos aplicados: se corrige con movimientos de ajuste/cancelación.

---

## Alcance MVP recomendado

1. Catálogo de conceptos de cobro.
2. Cargos por alumno (manual o masivo).
3. Registro de pagos (total/parcial).
4. Aplicación de pago a cargos (FIFO o manual).
5. Estado de cuenta por alumno/tutor.
6. Bandeja de cartera vencida con semáforo.
7. Recordatorios automáticos (in-app inicialmente).

---

## Modelo de datos propuesto

> Todas las tablas con `tenant_id` y timestamps.

### 1) `finance_concepts`
- `id`
- `tenant_id`
- `campus_id` (nullable para concepto global)
- `code` (único por tenant)
- `name`
- `description` (nullable)
- `default_amount` decimal(10,2) nullable
- `is_active` boolean

### 2) `finance_charges`
- `id`
- `tenant_id`
- `campus_id`
- `school_cycle_id` nullable
- `student_id`
- `concept_id`
- `reference` (folio interno)
- `description`
- `amount` decimal(10,2)
- `due_date` date
- `issued_at` datetime
- `status` enum: `pending|partial|paid|cancelled`
- `created_by`

### 3) `finance_payments`
- `id`
- `tenant_id`
- `campus_id`
- `student_id`
- `payment_date` datetime
- `amount` decimal(10,2)
- `method` enum: `cash|transfer|card|other`
- `reference` nullable
- `notes` nullable
- `status` enum: `applied|partially_applied|unapplied|cancelled`
- `created_by`

### 4) `finance_payment_applications`
- `id`
- `tenant_id`
- `payment_id`
- `charge_id`
- `applied_amount` decimal(10,2)
- `applied_at` datetime
- `created_by`

### 5) `finance_reminders` (opcional MVP+)
- `id`
- `tenant_id`
- `student_id`
- `guardian_user_id` nullable
- `charge_id` nullable
- `channel` enum: `in_app|email|whatsapp`
- `scheduled_for` datetime
- `sent_at` nullable
- `status` enum: `pending|sent|failed|cancelled`

---

## Reglas de negocio (recomendadas)

1. **Saldo de cargo** = `amount - SUM(applied_amount)`.
2. **Estado de cargo**:
   - `pending`: sin aplicaciones.
   - `partial`: saldo > 0 y hubo aplicaciones.
   - `paid`: saldo = 0.
   - `cancelled`: anulado administrativamente.
3. **Estado de pago**:
   - `unapplied`: no aplicado.
   - `partially_applied`: aplicado parcialmente.
   - `applied`: aplicado total.
4. **No borrar pagos/cargos** con movimiento aplicado.
5. **Permitido pago parcial**.
6. **Cálculo de mora** por días vencidos:
   - `0`: al corriente
   - `1-30`: vencido bajo
   - `31-60`: vencido medio
   - `61+`: vencido alto

---

## UI mínima (coordinación/admin)

1. **Cartera vencida (bandeja)**
   - Filtros: campus, ciclo, grupo, tutor, rango de atraso.
   - Columnas: alumno, tutor, saldo total, cargos vencidos, días máx. vencido, acción.

2. **Estado de cuenta alumno**
   - Resumen (saldo total, vencido, por vencer).
   - Tabla de cargos.
   - Tabla de pagos/aplicaciones.
   - Botón registrar pago.

3. **Catálogo de conceptos**
   - CRUD simple.

4. **Carga masiva de cargos**
   - Por grupo/ciclo/concepto/importe/fecha de vencimiento.

---

## Permisos sugeridos

- `finance.view_dashboard`
- `finance.manage_concepts`
- `finance.create_charge`
- `finance.apply_payment`
- `finance.cancel_movement`
- `finance.send_reminders`
- `finance.view_student_statement`

Roles:
- Coordinador/admin: completo.
- Tutor: solo consulta de sus alumnos.

---

## Integración con tutores (sin acoplar académico)

1. Nueva sección en portal tutor: **Estado de cuenta**.
2. Indicador simple: “Tienes N cargos vencidos”.
3. Recordatorios internos con links directos al estado de cuenta.

---

## Estrategia de implementación por fases

### Fase 1 (1 sprint)
- Migraciones + modelos + CRUD conceptos.
- Cargos manuales + masivos.
- Pagos + aplicación manual.
- Estado de cuenta básico.

### Fase 2
- Autoaplicación FIFO opcional.
- Recordatorios automáticos.
- Exportación PDF/Excel.

### Fase 3
- Recargos automáticos, convenios y reportes ejecutivos.

---

## Checklist de no-regresión

Antes de merge:
1. Probar rutas de clases/asistencia/calificaciones sin cambios.
2. Verificar que no se tocaron tablas académicas existentes.
3. Confirmar filtros por `tenant_id` + `campus_id` en consultas del módulo.
4. Validar que usuarios sin permisos financieros no ven el módulo.

