# Piloto demo coordinacion

## Objetivo de la presentacion

Mostrar que el sistema ya puede operar como centro de control academico: asistencia, documentos docentes, evaluacion, reportes, seguimiento y casos escolares conectados en un mismo flujo.

## Recorrido sugerido

1. Abrir `Dashboard`.
   - Mostrar el bloque `Piloto operativo listo para demostracion`.
   - Enfatizar alumnos, grupos, sesiones cerradas, registros de asistencia, actividades, calificaciones, reportes y casos.

2. Abrir `Expediente docente`.
   - Mostrar porcentaje de entrega por profesor.
   - Entrar al detalle de un profesor.
   - Abrir PDF de un documento entregado.
   - Explicar que el sistema detecta temarios, planeaciones y examenes generados desde modulos internos.

3. Abrir `Fechas de captura / Actas`.
   - Mostrar parciales y ventanas de captura.
   - Explicar que coordinacion puede cerrar, recordar pendientes y controlar captura por parcial.

4. Abrir `Reportes`.
   - Mostrar reportes centralizados de profesores, alumnos, prefectura y coordinacion.
   - Levantar un reporte como coordinacion si quieren ver el flujo.
   - Convertir un reporte en caso escolar.

5. Abrir `Casos escolares`.
   - Mostrar estatus, responsable, fecha compromiso, acciones y notas.
   - Enseñar que el seguimiento no se queda en "ya lo vi", sino que asigna tareas y deja evidencia.

6. Abrir `Riesgo de asistencia` y `Alumnos que se saltan clase`.
   - Explicar el cruce entre asistencia de prefectura y asistencia docente.
   - Este es uno de los diferenciadores fuertes del piloto.

7. Entrar como profesor participante.
   - Mostrar `Mis clases`, sesiones, actividades y rubros.
   - Mostrar documentos solicitados y generacion/entrega de documentos.

## Datos demo

Los datos demo integrales se regeneran con:

```bash
php artisan demo:seed-final-reports --fresh --weeks=4 --per-group=25 --password=123123123
```

El comando crea alumnos y tutores ficticios, asistencias de prefectura, asistencias docentes, actividades, calificaciones, justificantes, suspensiones, reportes, seguimientos y casos escolares.

## Mensaje clave

El piloto no busca reemplazar todos los procesos desde el dia uno. Busca demostrar que la escuela puede dejar de operar con informacion fragmentada y empezar a coordinarse desde evidencia viva: quien asistio, quien falto, quien reporto, que se entrego, que falta y quien debe actuar.
