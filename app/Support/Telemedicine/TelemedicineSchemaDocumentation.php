<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Diccionario de datos y relaciones del módulo de telemedicina (esquema legado).
 *
 * @phpstan-type ColumnDefinition array{
 *     name: string,
 *     type: string,
 *     nullable: bool,
 *     default: string|null,
 *     description: string,
 * }
 * @phpstan-type TableDefinition array{
 *     slug: string,
 *     name: string,
 *     title: string,
 *     description: string,
 *     columns: list<ColumnDefinition>,
 *     notes: list<string>,
 * }
 * @phpstan-type RelationshipDefinition array{
 *     from: string,
 *     to: string,
 *     cardinality: string,
 *     label: string,
 *     via: string,
 * }
 */
final class TelemedicineSchemaDocumentation
{
    public const VERSION = '1.0.0';

    public const UPDATED_AT = '2026-05-25';

    /**
     * @return list<TableDefinition>
     */
    public static function tables(): array
    {
        return [
            self::telemedicinePatientsTable(),
            self::telemedicineCasesTable(),
            self::telemedicineConsultationPatientsTable(),
            self::telemedicineDocumentsTable(),
            self::telemedicineHistoryPatientsTable(),
            self::telemedicinePatientLabsTable(),
            self::telemedicinePatientMedicationsTable(),
            self::telemedicinePatientSpecialtiesTable(),
            self::telemedicinePatientStudiesTable(),
        ];
    }

    /**
     * @return list<RelationshipDefinition>
     */
    public static function relationships(): array
    {
        return [
            [
                'from' => 'telemedicine_patients',
                'to' => 'telemedicine_cases',
                'cardinality' => '1:N',
                'label' => 'casos',
                'via' => 'telemedicine_patient_id',
            ],
            [
                'from' => 'telemedicine_cases',
                'to' => 'telemedicine_consultation_patients',
                'cardinality' => '1:N',
                'label' => 'consultas',
                'via' => 'telemedicine_case_id',
            ],
            [
                'from' => 'telemedicine_cases',
                'to' => 'telemedicine_documents',
                'cardinality' => '1:N',
                'label' => 'documentos',
                'via' => 'telemedicine_case_id',
            ],
            [
                'from' => 'telemedicine_consultation_patients',
                'to' => 'telemedicine_documents',
                'cardinality' => '1:N',
                'label' => 'documentos',
                'via' => 'telemedicine_consultation_id',
            ],
            [
                'from' => 'telemedicine_patients',
                'to' => 'telemedicine_history_patients',
                'cardinality' => '1:N',
                'label' => 'historias',
                'via' => 'telemedicine_patient_id',
            ],
            [
                'from' => 'telemedicine_cases',
                'to' => 'telemedicine_patient_labs',
                'cardinality' => '1:N',
                'label' => 'laboratorios',
                'via' => 'telemedicine_case_id',
            ],
            [
                'from' => 'telemedicine_consultation_patients',
                'to' => 'telemedicine_patient_labs',
                'cardinality' => '1:N',
                'label' => 'laboratorios',
                'via' => 'telemedicine_consultation_patient_id',
            ],
            [
                'from' => 'telemedicine_cases',
                'to' => 'telemedicine_patient_medications',
                'cardinality' => '1:N',
                'label' => 'medicamentos',
                'via' => 'telemedicine_case_id',
            ],
            [
                'from' => 'telemedicine_consultation_patients',
                'to' => 'telemedicine_patient_medications',
                'cardinality' => '1:N',
                'label' => 'medicamentos',
                'via' => 'telemedicine_consultation_patient_id',
            ],
            [
                'from' => 'telemedicine_cases',
                'to' => 'telemedicine_patient_specialties',
                'cardinality' => '1:N',
                'label' => 'especialistas',
                'via' => 'telemedicine_case_id',
            ],
            [
                'from' => 'telemedicine_consultation_patients',
                'to' => 'telemedicine_patient_specialties',
                'cardinality' => '1:N',
                'label' => 'especialistas',
                'via' => 'telemedicine_consultation_patient_id',
            ],
            [
                'from' => 'telemedicine_cases',
                'to' => 'telemedicine_patient_studies',
                'cardinality' => '1:N',
                'label' => 'estudios',
                'via' => 'telemedicine_case_id',
            ],
            [
                'from' => 'telemedicine_consultation_patients',
                'to' => 'telemedicine_patient_studies',
                'cardinality' => '1:N',
                'label' => 'estudios',
                'via' => 'telemedicine_consultation_patient_id',
            ],
        ];
    }

    public static function mermaidErDiagram(): string
    {
        $lines = [
            'erDiagram',
            '    telemedicine_patients ||--o{ telemedicine_cases : "telemedicine_patient_id"',
            '    telemedicine_cases ||--o{ telemedicine_consultation_patients : "telemedicine_case_id"',
            '    telemedicine_cases ||--o{ telemedicine_documents : "telemedicine_case_id"',
            '    telemedicine_consultation_patients ||--o{ telemedicine_documents : "telemedicine_consultation_id"',
            '    telemedicine_patients ||--o{ telemedicine_history_patients : "telemedicine_patient_id"',
            '    telemedicine_cases ||--o{ telemedicine_patient_labs : "telemedicine_case_id"',
            '    telemedicine_consultation_patients ||--o{ telemedicine_patient_labs : "telemedicine_consultation_patient_id"',
            '    telemedicine_cases ||--o{ telemedicine_patient_medications : "telemedicine_case_id"',
            '    telemedicine_consultation_patients ||--o{ telemedicine_patient_medications : "telemedicine_consultation_patient_id"',
            '    telemedicine_cases ||--o{ telemedicine_patient_specialties : "telemedicine_case_id"',
            '    telemedicine_consultation_patients ||--o{ telemedicine_patient_specialties : "telemedicine_consultation_patient_id"',
            '    telemedicine_cases ||--o{ telemedicine_patient_studies : "telemedicine_case_id"',
            '    telemedicine_consultation_patients ||--o{ telemedicine_patient_studies : "telemedicine_consultation_patient_id"',
            '',
            '    telemedicine_patients {',
            '        bigint id PK',
            '        varchar full_name',
            '        varchar email UK',
            '        varchar nro_identificacion',
            '    }',
            '',
            '    telemedicine_cases {',
            '        bigint id PK',
            '        varchar code',
            '        int telemedicine_patient_id FK',
            '        int telemedicine_doctor_id FK',
            '        varchar status',
            '    }',
            '',
            '    telemedicine_consultation_patients {',
            '        bigint id PK',
            '        int telemedicine_case_id FK',
            '        varchar telemedicine_case_code',
            '        json uploaded_documents',
            '    }',
            '',
            '    telemedicine_documents {',
            '        bigint id PK',
            '        int telemedicine_case_id FK',
            '        int telemedicine_consultation_id FK',
            '        varchar name',
            '    }',
            '',
            '    telemedicine_history_patients {',
            '        bigint id PK',
            '        varchar code UK',
            '        int telemedicine_patient_id FK',
            '    }',
            '',
            '    telemedicine_patient_labs {',
            '        bigint id PK',
            '        varchar laboratory',
            '        varchar status',
            '    }',
            '',
            '    telemedicine_patient_medications {',
            '        bigint id PK',
            '        varchar medicine',
            '        varchar status',
            '    }',
            '',
            '    telemedicine_patient_specialties {',
            '        bigint id PK',
            '        varchar specialty',
            '        varchar status',
            '    }',
            '',
            '    telemedicine_patient_studies {',
            '        bigint id PK',
            '        varchar study',
            '        varchar status',
            '    }',
        ];

        return implode("\n", $lines);
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicinePatientsTable(): array
    {
        return [
            'slug' => 'telemedicine-patients',
            'name' => 'telemedicine_patients',
            'title' => 'Pacientes de telemedicina',
            'description' => 'Registro maestro del paciente atendido en el módulo. Vincula datos demográficos, afiliación y unidad de negocio.',
            'notes' => [
                'El correo (`email`) tiene restricción UNIQUE.',
                'Los identificadores geográficos (`city_id`, `country_id`, `state_id`) se almacenan como varchar en el esquema legado.',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario autoincremental.'),
                self::col('full_name', 'varchar(100)', true, null, 'Nombre completo del paciente.'),
                self::col('plan_id', 'int', true, null, 'Plan de cobertura asociado.'),
                self::col('afilliation_id', 'int', true, null, 'Afiliación individual (ortografía legada: afilliation).'),
                self::col('afilliation_corporate_id', 'int', true, null, 'Afiliación corporativa.'),
                self::col('nro_identificacion', 'varchar(255)', true, null, 'Documento de identidad.'),
                self::col('birth_date', 'varchar(255)', false, null, 'Fecha de nacimiento (texto).'),
                self::col('sex', 'varchar(255)', false, null, 'Sexo biológico o registrado.'),
                self::col('phone', 'varchar(255)', false, null, 'Teléfono principal.'),
                self::col('email', 'varchar(255)', false, null, 'Correo electrónico (único).'),
                self::col('address', 'varchar(255)', false, null, 'Dirección de residencia.'),
                self::col('city_id', 'varchar(255)', false, null, 'Ciudad (referencia legada).'),
                self::col('country_id', 'varchar(255)', false, null, 'País (referencia legada).'),
                self::col('region', 'varchar(255)', false, null, 'Región.'),
                self::col('state_id', 'varchar(255)', false, null, 'Estado o provincia (referencia legada).'),
                self::col('phone_contact', 'varchar(255)', true, null, 'Teléfono de contacto alternativo.'),
                self::col('email_contact', 'varchar(255)', true, null, 'Correo de contacto alternativo.'),
                self::col('type_affiliation', 'varchar(255)', true, null, 'Tipo de afiliación.'),
                self::col('date_affiliation', 'varchar(255)', true, null, 'Fecha de afiliación.'),
                self::col('status_affiliation', 'varchar(255)', true, null, 'Estado de la afiliación.'),
                self::col('created_at', 'timestamp', true, null, 'Marca de creación.'),
                self::col('updated_at', 'timestamp', true, null, 'Última actualización.'),
                self::col('code', 'varchar(100)', true, null, 'Código interno del paciente.'),
                self::col('age', 'int', true, null, 'Edad calculada o registrada.'),
                self::col('created_by', 'varchar(100)', true, null, 'Usuario o proceso que creó el registro.'),
                self::col('coverage_id', 'varchar(100)', true, null, 'Identificador de cobertura.'),
                self::col('code_affiliation', 'varchar(100)', true, null, 'Código de afiliación.'),
                self::col('type', 'varchar(100)', true, 'PACIENTE', 'Tipo de registro (por defecto PACIENTE).'),
                self::col('business_unit_id', 'varchar(10)', true, null, 'Unidad de negocio.'),
                self::col('business_line_id', 'varchar(10)', true, null, 'Línea de negocio.'),
                self::col('name_corporate', 'varchar(100)', true, null, 'Nombre de la empresa corporativa.'),
                self::col('managed_by', 'varchar(100)', false, 'TDG', 'Gestor del registro (p. ej. TDG, ATENMEDI).'),
            ],
        ];
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicineCasesTable(): array
    {
        return [
            'slug' => 'telemedicine-cases',
            'name' => 'telemedicine_cases',
            'title' => 'Casos de telemedicina',
            'description' => 'Incidente o solicitud de atención asignada a un médico. Centraliza el estado del caso y datos de contacto del paciente en el momento del caso.',
            'notes' => [
                'El estado por defecto es ASIGNADO.',
                'Referencia a `telemedicine_doctor_id` (tabla externa al presente diccionario).',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario.'),
                self::col('code', 'varchar(100)', true, null, 'Código legible del caso.'),
                self::col('telemedicine_patient_id', 'int', false, null, 'FK lógica al paciente.'),
                self::col('telemedicine_doctor_id', 'int', false, null, 'FK lógica al médico asignado.'),
                self::col('patient_name', 'varchar(255)', true, null, 'Nombre del paciente (snapshot).'),
                self::col('patient_age', 'varchar(255)', true, null, 'Edad al momento del caso.'),
                self::col('patient_sex', 'varchar(255)', true, null, 'Sexo (snapshot).'),
                self::col('patient_phone', 'varchar(255)', true, null, 'Teléfono principal.'),
                self::col('patient_address', 'varchar(255)', true, null, 'Dirección.'),
                self::col('patient_country_id', 'int', true, null, 'País del paciente en el caso.'),
                self::col('patient_state_id', 'int', true, null, 'Estado o provincia.'),
                self::col('patient_city_id', 'int', true, null, 'Ciudad.'),
                self::col('assigned_by', 'varchar(255)', true, null, 'Quién asignó el caso.'),
                self::col('status', 'varchar(255)', false, 'ASIGNADO', 'Estado operativo del caso.'),
                self::col('created_at', 'timestamp', true, null, 'Creación del registro.'),
                self::col('updated_at', 'timestamp', true, null, 'Última modificación.'),
                self::col('reason', 'longtext', true, null, 'Motivo de consulta o incidente.'),
                self::col('telemedicine_priority_id', 'int', true, null, 'Prioridad asignada.'),
                self::col('patient_phone_2', 'varchar(100)', true, null, 'Teléfono secundario.'),
                self::col('ambulanceParking', 'tinyint(1)', true, null, 'Indica si requiere estacionamiento de ambulancia.'),
                self::col('directionAmbulance', 'longtext', true, null, 'Indicaciones para ambulancia.'),
                self::col('managed_by', 'varchar(100)', true, null, 'Gestor del caso (TDG, ATENMEDI, etc.).'),
                self::col('doctor_id_first_accompaniment', 'int', true, null, 'Médico del primer acompañamiento.'),
            ],
        ];
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicineConsultationPatientsTable(): array
    {
        return [
            'slug' => 'telemedicine-consultation-patients',
            'name' => 'telemedicine_consultation_patients',
            'title' => 'Consultas del paciente',
            'description' => 'Nota clínica y formulario de consulta vinculado a un caso. Incluye signos vitales, impresión diagnóstica y catálogos JSON de órdenes.',
            'notes' => [
                'Varios campos (`labs`, `studies`, `consult_specialist`, etc.) tienen CHECK json_valid en MySQL.',
                'El campo `actual_phatology` conserva la ortografía del esquema legado.',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario.'),
                self::col('telemedicine_case_id', 'int', false, null, 'FK lógica al caso.'),
                self::col('telemedicine_case_code', 'varchar(10)', false, null, 'Código del caso (redundante para consultas).'),
                self::col('telemedicine_patient_id', 'int', false, null, 'FK lógica al paciente.'),
                self::col('telemedicine_doctor_id', 'int', false, null, 'FK lógica al médico.'),
                self::col('telemedicine_priority_id', 'int', true, null, 'Prioridad de la consulta.'),
                self::col('telemedicine_service_list_id', 'int', true, null, 'Servicio de la lista maestra.'),
                self::col('code_reference', 'varchar(255)', true, null, 'Código de referencia externo.'),
                self::col('full_name', 'varchar(255)', true, null, 'Nombre en la consulta.'),
                self::col('nro_identificacion', 'varchar(255)', true, null, 'Identificación en la consulta.'),
                self::col('reason_consultation', 'longtext', true, null, 'Motivo de consulta.'),
                self::col('actual_phatology', 'longtext', true, null, 'Patología actual.'),
                self::col('background', 'longtext', true, null, 'Antecedentes.'),
                self::col('diagnostic_impression', 'longtext', true, null, 'Impresión diagnóstica.'),
                self::col('labs', 'longtext (JSON)', true, null, 'Laboratorios solicitados (JSON).'),
                self::col('studies', 'longtext (JSON)', true, null, 'Estudios solicitados (JSON).'),
                self::col('consult_specialist', 'longtext (JSON)', true, null, 'Especialistas solicitados (JSON).'),
                self::col('other_labs', 'longtext (JSON)', true, null, 'Otros laboratorios (JSON).'),
                self::col('other_studies', 'longtext (JSON)', true, null, 'Otros estudios (JSON).'),
                self::col('other_specialist', 'longtext (JSON)', true, null, 'Otros especialistas (JSON).'),
                self::col('created_at', 'timestamp', true, null, 'Creación.'),
                self::col('updated_at', 'timestamp', true, null, 'Actualización.'),
                self::col('status', 'varchar(100)', true, null, 'Estado de la consulta.'),
                self::col('assigned_by', 'int', true, null, 'Usuario asignador (ID numérico).'),
                self::col('cuestion_1', 'longtext', true, null, 'Pregunta 1 del cuestionario.'),
                self::col('cuestion_2', 'longtext', true, null, 'Pregunta 2 del cuestionario.'),
                self::col('cuestion_3', 'longtext', true, null, 'Pregunta 3 del cuestionario.'),
                self::col('cuestion_4', 'longtext', true, null, 'Pregunta 4 del cuestionario.'),
                self::col('cuestion_5', 'longtext', true, null, 'Pregunta 5 del cuestionario.'),
                self::col('feedbackOne', 'tinyint(1)', true, null, 'Retroalimentación binaria.'),
                self::col('duration', 'int', true, null, 'Duración de la consulta.'),
                self::col('priorityMonitoring', 'int', true, null, 'Prioridad de monitoreo.'),
                self::col('observations', 'longtext', true, null, 'Observaciones generales.'),
                self::col('pa', 'decimal(8,2)', true, null, 'Presión arterial.'),
                self::col('fc', 'decimal(8,2)', true, null, 'Frecuencia cardiaca.'),
                self::col('fr', 'decimal(8,2)', true, null, 'Frecuencia respiratoria.'),
                self::col('temp', 'decimal(8,2)', true, null, 'Temperatura.'),
                self::col('saturacion', 'decimal(8,2)', true, null, 'Saturación de oxígeno.'),
                self::col('peso', 'decimal(8,2)', true, null, 'Peso.'),
                self::col('estatura', 'decimal(8,2)', true, null, 'Estatura.'),
                self::col('imc', 'decimal(8,2)', true, null, 'Índice de masa corporal.'),
                self::col('telemedicine_service_list_drift_id', 'int unsigned', true, null, 'Servicio derivado.'),
                self::col('uploaded_documents', 'json', true, null, 'Documentos cargados en la consulta.'),
            ],
        ];
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicineDocumentsTable(): array
    {
        return [
            'slug' => 'telemedicine-documents',
            'name' => 'telemedicine_documents',
            'title' => 'Documentos adjuntos',
            'description' => 'Archivos asociados a un caso y a una consulta concreta.',
            'notes' => [
                'El icono por defecto en `image` es pdf.png.',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario.'),
                self::col('telemedicine_case_id', 'int', false, null, 'FK lógica al caso.'),
                self::col('telemedicine_consultation_id', 'int', false, null, 'FK lógica a la consulta.'),
                self::col('telemedicine_patient_id', 'int', false, null, 'FK lógica al paciente.'),
                self::col('name', 'varchar(255)', false, null, 'Nombre del archivo o título.'),
                self::col('created_at', 'timestamp', true, null, 'Creación.'),
                self::col('updated_at', 'timestamp', true, null, 'Actualización.'),
                self::col('image', 'varchar(100)', true, 'pdf.png', 'Icono o miniatura representativa.'),
            ],
        ];
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicineHistoryPatientsTable(): array
    {
        return [
            'slug' => 'telemedicine-history-patients',
            'name' => 'telemedicine_history_patients',
            'title' => 'Historia clínica del paciente',
            'description' => 'Antecedentes patológicos, ginecológicos, alergias y hábitos. Formulario extenso con banderas tinyint y campos de texto libre.',
            'notes' => [
                'El campo `code` es UNIQUE (`telemedicine_history_patients_cod_history_unique`).',
                'El campo `allergies` valida JSON en MySQL.',
                'Existen columnas duplicadas en el esquema legado (p. ej. transfusiones_sanguineas).',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario.'),
                self::col('telemedicine_doctor_id', 'int', true, null, 'Médico que registró la historia.'),
                self::col('telemedicine_patient_id', 'int', false, null, 'FK lógica al paciente.'),
                self::col('code', 'varchar(255)', false, null, 'Código único de historia.'),
                self::col('user_id', 'varchar(255)', true, null, 'Usuario asociado.'),
                self::col('history_date', 'varchar(255)', false, null, 'Fecha de la historia.'),
                self::col('tension_alta', 'tinyint(1)', true, null, 'Antecedente: hipertensión.'),
                self::col('asma', 'tinyint(1)', true, null, 'Antecedente: asma.'),
                self::col('cardiacos', 'tinyint(1)', true, null, 'Antecedente: cardíacos.'),
                self::col('gastritis_ulceras', 'tinyint(1)', true, null, 'Antecedente: gastritis/úlceras.'),
                self::col('enfermedad_autoimmune', 'tinyint(1)', true, null, 'Enfermedad autoinmune.'),
                self::col('trombosis_embooleanas', 'tinyint(1)', true, null, 'Trombosis/embolias.'),
                self::col('fracturas', 'tinyint(1)', true, null, 'Fracturas.'),
                self::col('cancer', 'tinyint(1)', true, null, 'Cáncer.'),
                self::col('tranfusiones_sanguineas', 'tinyint(1)', true, null, 'Transfusiones (columna legada).'),
                self::col('tiroides', 'tinyint(1)', true, null, 'Tiroides.'),
                self::col('hepatitis', 'tinyint(1)', true, null, 'Hepatitis.'),
                self::col('moretones_frecuentes', 'tinyint(1)', true, null, 'Moretones frecuentes.'),
                self::col('psiquiatricas', 'tinyint(1)', true, null, 'Condiciones psiquiátricas.'),
                self::col('covid', 'tinyint(1)', true, null, 'COVID-19.'),
                self::col('diabetes', 'tinyint(1)', true, null, 'Diabetes.'),
                self::col('alteraciones_coagulacion', 'tinyint(1)', true, null, 'Alteraciones de coagulación.'),
                self::col('vih', 'tinyint(1)', true, null, 'VIH.'),
                self::col('neurologia', 'tinyint(1)', true, null, 'Neurología.'),
                self::col('ansiedad_angustia', 'tinyint(1)', true, null, 'Ansiedad/angustia.'),
                self::col('lupus', 'tinyint(1)', true, null, 'Lupus.'),
                self::col('diabetes_mellitus', 'tinyint(1)', true, null, 'Diabetes mellitus.'),
                self::col('presion_arterial_alta', 'tinyint(1)', true, null, 'Presión arterial alta.'),
                self::col('tiene_cateter_venoso', 'tinyint(1)', true, null, 'Catéter venoso.'),
                self::col('trombosis_venosa', 'tinyint(1)', true, null, 'Trombosis venosa.'),
                self::col('embooleania_pulmonar', 'tinyint(1)', true, null, 'Embolia pulmonar.'),
                self::col('varices_piernas', 'tinyint(1)', true, null, 'Várices en piernas.'),
                self::col('insuficiencia_arterial', 'tinyint(1)', true, null, 'Insuficiencia arterial.'),
                self::col('coagulacion_anormal', 'tinyint(1)', true, null, 'Coagulación anormal.'),
                self::col('sangrado_cirugias_previas', 'tinyint(1)', true, null, 'Sangrado en cirugías previas.'),
                self::col('sangrado_cepillado_dental', 'tinyint(1)', true, null, 'Sangrado al cepillarse.'),
                self::col('alcohol', 'tinyint(1)', true, null, 'Consumo de alcohol.'),
                self::col('drogas', 'tinyint(1)', true, null, 'Uso de drogas.'),
                self::col('vacunas_recientes', 'tinyint(1)', true, null, 'Vacunas recientes.'),
                self::col('transfusiones_sanguineas', 'tinyint(1)', true, null, 'Transfusiones sanguíneas.'),
                self::col('edad_primera_menstruation', 'varchar(255)', true, null, 'Edad de primera menstruación.'),
                self::col('fecha_ultima_regla', 'varchar(255)', true, null, 'Fecha de última regla.'),
                self::col('numero_embarazos', 'int', true, null, 'Número de embarazos.'),
                self::col('numero_partos', 'int', true, null, 'Número de partos.'),
                self::col('numero_abortos', 'int', true, null, 'Número de abortos.'),
                self::col('cesareas', 'int', true, null, 'Número de cesáreas.'),
                self::col('allergies', 'longtext (JSON)', true, null, 'Alergias en JSON.'),
                self::col('history_surgical', 'longtext', true, null, 'Antecedentes quirúrgicos.'),
                self::col('medications_supplements', 'longtext', true, null, 'Medicamentos y suplementos.'),
                self::col('observations_ginecologica', 'longtext', true, null, 'Observaciones ginecológicas.'),
                self::col('observations_allergies', 'longtext', true, null, 'Observaciones de alergias.'),
                self::col('observations_medication', 'longtext', true, null, 'Observaciones de medicación.'),
                self::col('observations_personal', 'longtext', true, null, 'Observaciones personales.'),
                self::col('observations_diagnosis', 'longtext', true, null, 'Observaciones de diagnóstico.'),
                self::col('observations_not_pathological', 'longtext', true, null, 'Antecedentes no patológicos.'),
                self::col('created_by', 'varchar(255)', false, null, 'Autor del registro.'),
                self::col('created_at', 'timestamp', true, null, 'Creación.'),
                self::col('updated_at', 'timestamp', true, null, 'Actualización.'),
                self::col('observations_pathological', 'longtext', true, null, 'Antecedentes patológicos (texto).'),
                self::col('updated_by', 'varchar(100)', true, null, 'Último editor.'),
            ],
        ];
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicinePatientLabsTable(): array
    {
        return [
            'slug' => 'telemedicine-patient-labs',
            'name' => 'telemedicine_patient_labs',
            'title' => 'Órdenes de laboratorio',
            'description' => 'Laboratorios solicitados durante un caso/consulta, con seguimiento operativo.',
            'notes' => [
                'Estado por defecto: PENDIENTE.',
                'Puede vincularse a `operation_coordination_service_id` para coordinación operativa.',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario.'),
                self::col('telemedicine_patient_id', 'int', false, null, 'FK lógica al paciente.'),
                self::col('telemedicine_case_id', 'int', false, null, 'FK lógica al caso.'),
                self::col('telemedicine_doctor_id', 'int', false, null, 'FK lógica al médico.'),
                self::col('telemedicine_consultation_patient_id', 'int', false, null, 'FK lógica a la consulta.'),
                self::col('laboratory', 'varchar(255)', false, null, 'Nombre o código del laboratorio.'),
                self::col('type', 'varchar(255)', false, null, 'Tipo de orden.'),
                self::col('created_at', 'timestamp', true, null, 'Creación.'),
                self::col('updated_at', 'timestamp', true, null, 'Actualización.'),
                self::col('assigned_by', 'varchar(100)', true, null, 'Asignado por.'),
                self::col('operation_coordination_service_id', 'int', true, null, 'Servicio de coordinación vinculado.'),
                self::col('status', 'varchar(100)', false, 'PENDIENTE', 'Estado de la orden.'),
            ],
        ];
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicinePatientMedicationsTable(): array
    {
        return [
            'slug' => 'telemedicine-patient-medications',
            'name' => 'telemedicine_patient_medications',
            'title' => 'Medicamentos indicados',
            'description' => 'Recetas e indicaciones farmacológicas asociadas a caso, consulta o seguimiento.',
            'notes' => [
                'Estado por defecto: PENDIENTE.',
                'Soporta cobertura (`is_covered`) e inventario operativo.',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario.'),
                self::col('telemedicine_patient_id', 'int', false, null, 'FK lógica al paciente.'),
                self::col('telemedicine_case_id', 'int', false, null, 'FK lógica al caso.'),
                self::col('telemedicine_doctor_id', 'int', false, null, 'FK lógica al médico.'),
                self::col('telemedicine_consultation_patient_id', 'int', true, null, 'FK lógica a la consulta.'),
                self::col('telemedicine_follow_up_id', 'int', true, null, 'FK lógica a seguimiento.'),
                self::col('medicine', 'varchar(255)', false, null, 'Medicamento indicado.'),
                self::col('indications', 'longtext', false, null, 'Indicaciones de uso.'),
                self::col('created_at', 'timestamp', true, null, 'Creación.'),
                self::col('updated_at', 'timestamp', true, null, 'Actualización.'),
                self::col('type', 'varchar(100)', true, null, 'Tipo de indicación.'),
                self::col('assigned_by', 'varchar(100)', true, null, 'Asignado por.'),
                self::col('duration', 'int', true, null, 'Duración del tratamiento.'),
                self::col('telemedicine_priority_id', 'int', true, null, 'Prioridad.'),
                self::col('operation_coordination_service_id', 'int', true, null, 'Servicio de coordinación.'),
                self::col('status', 'varchar(100)', false, 'PENDIENTE', 'Estado de despacho o gestión.'),
                self::col('operation_inventory_id', 'int', true, null, 'Inventario operativo.'),
                self::col('is_covered', 'tinyint(1)', true, null, 'Indica si está cubierto por el plan.'),
            ],
        ];
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicinePatientSpecialtiesTable(): array
    {
        return [
            'slug' => 'telemedicine-patient-specialties',
            'name' => 'telemedicine_patient_specialties',
            'title' => 'Referencias a especialistas',
            'description' => 'Interconsultas o derivaciones a especialidades médicas.',
            'notes' => [
                'Estado por defecto: PENDIENTE.',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario.'),
                self::col('telemedicine_patient_id', 'int', false, null, 'FK lógica al paciente.'),
                self::col('telemedicine_case_id', 'int', false, null, 'FK lógica al caso.'),
                self::col('telemedicine_doctor_id', 'int', false, null, 'FK lógica al médico.'),
                self::col('telemedicine_consultation_patient_id', 'int', false, null, 'FK lógica a la consulta.'),
                self::col('type', 'varchar(255)', false, null, 'Tipo de referencia.'),
                self::col('specialty', 'varchar(255)', false, null, 'Especialidad solicitada.'),
                self::col('assigned_by', 'varchar(255)', false, null, 'Asignado por.'),
                self::col('created_at', 'timestamp', true, null, 'Creación.'),
                self::col('updated_at', 'timestamp', true, null, 'Actualización.'),
                self::col('operation_coordination_service_id', 'int', true, null, 'Servicio de coordinación.'),
                self::col('status', 'varchar(100)', false, 'PENDIENTE', 'Estado de la referencia.'),
            ],
        ];
    }

    /**
     * @return TableDefinition
     */
    private static function telemedicinePatientStudiesTable(): array
    {
        return [
            'slug' => 'telemedicine-patient-studies',
            'name' => 'telemedicine_patient_studies',
            'title' => 'Estudios de imagen u otros',
            'description' => 'Órdenes de estudios (imagenología, etc.) vinculadas al flujo del caso.',
            'notes' => [
                'Estado por defecto: PENDIENTE.',
            ],
            'columns' => [
                self::col('id', 'bigint unsigned', false, null, 'Identificador primario.'),
                self::col('telemedicine_patient_id', 'int', false, null, 'FK lógica al paciente.'),
                self::col('telemedicine_case_id', 'int', false, null, 'FK lógica al caso.'),
                self::col('telemedicine_doctor_id', 'int', false, null, 'FK lógica al médico.'),
                self::col('telemedicine_consultation_patient_id', 'int', false, null, 'FK lógica a la consulta.'),
                self::col('study', 'varchar(255)', false, null, 'Estudio solicitado.'),
                self::col('type', 'varchar(255)', false, null, 'Tipo de estudio.'),
                self::col('created_at', 'timestamp', true, null, 'Creación.'),
                self::col('updated_at', 'timestamp', true, null, 'Actualización.'),
                self::col('assigned_by', 'varchar(100)', true, null, 'Asignado por.'),
                self::col('operation_coordination_service_id', 'int', true, null, 'Servicio de coordinación.'),
                self::col('status', 'varchar(100)', false, 'PENDIENTE', 'Estado de la orden.'),
            ],
        ];
    }

    /**
     * @return ColumnDefinition
     */
    private static function col(
        string $name,
        string $type,
        bool $nullable,
        ?string $default,
        string $description,
    ): array {
        return [
            'name' => $name,
            'type' => $type,
            'nullable' => $nullable,
            'default' => $default,
            'description' => $description,
        ];
    }
}
