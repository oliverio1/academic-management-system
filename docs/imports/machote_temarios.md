# Machote para generar temarios

Usa este formato cuando se pida, redacte o convierta un temario para cargarlo al sistema. La estructura importante es la numeracion: unidad `1`, tema `1.1` y subtema `1.1.1`.

## Datos de la materia

- Materia:
- Nivel:
- Grado / cuatrimestre:
- Ciclo:
- Horas:
- Creditos:
- Componente / campo:
- Fuente:

## Objetivo general

Escribe aqui el objetivo general del curso en un solo parrafo claro.

## Temario

### 1. Nombre de la unidad

Objetivo especifico de la unidad: escribe aqui que debe lograr el alumno al terminar esta unidad.

1.1 Nombre del tema
1.1.1 Subtema
1.1.2 Subtema
1.1.3 Subtema

1.2 Nombre del tema
1.2.1 Subtema
1.2.2 Subtema

### 2. Nombre de la unidad

Objetivo especifico de la unidad: escribe aqui que debe lograr el alumno al terminar esta unidad.

2.1 Nombre del tema
2.1.1 Subtema
2.1.2 Subtema

2.2 Nombre del tema
2.2.1 Subtema
2.2.2 Subtema

## Reglas para que el sistema lo importe bien

- Cada unidad debe empezar con un numero entero: `1.`, `2.`, `3.`.
- Cada tema debe usar dos niveles: `1.1`, `1.2`, `2.1`.
- Cada subtema debe usar tres niveles: `1.1.1`, `1.1.2`, `2.1.1`.
- No mezclar varios temas en una sola linea.
- Evitar encabezados administrativos dentro del listado: bibliografia, competencias genericas, datos de portada, firmas, rubricas.
- Si el PDF solo trae "Bloques de aprendizaje", usar cada bloque como unidad y repetirlo como tema principal.
- Si no hay subtemas, dejar al menos un tema por unidad.

## Formato OLICATI para Excel importable

La plantilla de importacion del sistema usa una sola hoja:

- `A1`: etiqueta `Nombre de la materia`.
- `B1`: nombre de la materia.
- `A2`: etiqueta opcional, por ejemplo `Creditos`.
- `B2`: valor opcional, por ejemplo `8`.
- `A4`: etiqueta `Objetivo general`.
- `B4`: objetivo general.
- Desde `A5`: puntos del temario, uno por fila.
- En filas de unidad, columna `B`: objetivo especifico.
- En filas de unidad, columna `C`: horas de la unidad. El sistema las guarda en la base de datos.
- Para Preparatoria, la columna siguiente puede indicar tipo de contenido: `conceptual`, `procedimental`, `actitudinal` u `otro`.
- Si una fila de unidad usa `C` para horas y tambien necesita tipo, coloca el tipo en `D`.
- En el encabezado OLICATI se pueden agregar filas antes de `Objetivo general`: `Clave`, `Tipo`, `Horas por semana`, `Horas al año`. El importador actualiza esos datos en la materia cuando existen.

Ejemplo:

| Celda A | Celda B | Celda C | Celda D |
| --- | --- | --- | --- |
| Nombre de la materia | Fisica I |  |  |
| Creditos | 8 |  |  |
|  |  |  |  |
| Objetivo general | Comprender los principios basicos del movimiento, fuerzas y energia. |  |  |
| 1. Cinematica | Analizar el movimiento rectilineo y sus representaciones. | 12 | conceptual |
| 1.1. Magnitudes y unidades |  | conceptual |  |
| 1.2. Movimiento rectilineo uniforme |  | procedimental |  |
| 1.2.1. Graficas posicion-tiempo |  | actitudinal |  |
| 2. Dinamica | Aplicar las leyes de Newton para resolver problemas de fuerzas. | 10 | conceptual |
| 2.1. Leyes de Newton |  | conceptual |  |
| 2.1.1. Diagramas de cuerpo libre |  | procedimental |  |

## Prompt para generar un temario desde cero

Genera un temario escolar para la materia "[MATERIA]" de Bachillerato, grado/cuatrimestre "[GRADO]", con enfoque academico y lenguaje claro para docentes.

Entrega solo esta estructura:

1. Objetivo general del curso.
2. Unidades numeradas.
3. Objetivo especifico por unidad.
4. Temas con numeracion `1.1`, `1.2`, etc.
5. Subtemas con numeracion `1.1.1`, `1.1.2`, etc.

No incluyas portada, bibliografia, criterios de evaluacion, competencias genericas, firmas ni texto administrativo. Cada tema y subtema debe estar en una linea separada.
