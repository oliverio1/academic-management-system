<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Practice extends Model
{
    public const KIND_LABELS = [
        'practice' => 'Practica',
        'task' => 'Tarea',
        'project' => 'Proyecto',
    ];

    public const LEGACY_REPORT_FIELD_LABELS = [
        'objectives' => 'Objetivo',
        'hypothesis' => 'Hipotesis',
        'theoretical_framework' => 'Marco teorico',
        'results' => 'Resultados',
        'discussion' => 'Discusion',
        'conclusions' => 'Conclusiones',
        'references' => 'Referencias',
    ];

    protected $fillable = [
        'teaching_assignment_id',
        'activity_id',
        'number',
        'kind',
        'title',
        'introduction',
        'instructions',
        'procedure',
        'questionnaire',
        'submission_fields',
        'custom_submission_fields',
        'realization_date',
        'due_date',
    ];

    protected $casts = [
        'questionnaire' => 'array',
        'submission_fields' => 'array',
        'custom_submission_fields' => 'array',
        'realization_date' => 'date',
        'due_date' => 'date',
    ];

    public function getKindLabelAttribute(): string
    {
        return self::KIND_LABELS[$this->kind ?? 'practice'] ?? 'Entregable';
    }

    public function getDeliveryFieldDefinitionsAttribute(): array
    {
        if (is_array($this->custom_submission_fields) && ! empty($this->custom_submission_fields)) {
            return collect($this->custom_submission_fields)
                ->filter(fn ($field) => is_array($field) && ! empty($field['id']) && ! empty($field['label']))
                ->map(fn ($field) => [
                    'id' => (string) $field['id'],
                    'label' => (string) $field['label'],
                    'required' => (bool) ($field['required'] ?? false),
                    'legacy_field' => null,
                ])
                ->values()
                ->all();
        }

        $fields = is_array($this->submission_fields) && ! empty($this->submission_fields)
            ? $this->submission_fields
            : array_keys(self::LEGACY_REPORT_FIELD_LABELS);

        return collect($fields)
            ->filter(fn ($field) => array_key_exists($field, self::LEGACY_REPORT_FIELD_LABELS))
            ->map(fn ($field) => [
                'id' => $field,
                'label' => self::LEGACY_REPORT_FIELD_LABELS[$field],
                'required' => false,
                'legacy_field' => $field,
            ])
            ->values()
            ->all();
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function teachingAssignment()
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function submissions()
    {
        return $this->hasMany(PracticeSubmission::class);
    }
}
