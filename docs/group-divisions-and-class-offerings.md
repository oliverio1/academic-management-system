# Divisiones de grupo y ofertas académicas

## Tipos de división

- `level`: división por nivel. Uso actual: Inglés (`BASIC`, `ADVANCED`).
- `alphabetical`: división por orden alfabético (`SECTION_A`, `SECTION_B`).

## Regla para ofertas académicas

Una oferta académica sin registros en `class_offering_subgroups` aplica a toda la clase.

Una oferta con uno o más registros aplica solamente a esos subgrupos. El campo
`weekly_periods` indica cuántos periodos semanales corresponden a cada subgrupo.

### Inglés

Se recomienda crear una oferta académica por profesor y nivel:

- Inglés IV / profesor 1 / Básico / 3 periodos.
- Inglés IV / profesor 2 / Avanzado / 3 periodos.

Las dos ofertas deberán sincronizarse posteriormente mediante un bloque paralelo.

### Laboratorios y materias divididas

Cuando el mismo profesor atiende ambas secciones en horarios distintos, una oferta
puede vincularse con Sección A y Sección B, asignando los periodos correspondientes
a cada vínculo.

Cuando las secciones tienen profesores distintos, se recomienda crear una oferta
académica por profesor y subgrupo.

## Integración en `ClassOffering`

El modelo debe utilizar el trait:

```php
use App\Models\Concerns\HasGroupSubgroups;

class ClassOffering extends Model
{
    use HasGroupSubgroups;
}
```

Después estarán disponibles:

- `subgroupAssignments()`
- `groupSubgroups()`
- `activeGroupSubgroups()`
- `appliesToWholeGroup()`

## Ejemplo

```php
$classOffering->groupSubgroups()->attach($basicSubgroup->id, [
    'weekly_periods' => 3,
    'is_active' => true,
]);
```
