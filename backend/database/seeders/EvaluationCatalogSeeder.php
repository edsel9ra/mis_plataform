<?php

namespace Database\Seeders;

use App\Models\Assessment;
use Illuminate\Database\Seeder;

class EvaluationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->evaluations() as $evaluation) {
            Assessment::updateOrCreate(
                ['slug' => $evaluation['slug']],
                $evaluation + [
                    'module_id' => null,
                    'passing_score' => 70,
                    'is_active' => true,
                    'metadata' => ['source' => 'community_reference'],
                ],
            );
        }
    }

    private function evaluations(): array
    {
        $likert = ['Nunca o casi nunca', 'Raramente', 'A veces', 'Frecuentemente', 'Siempre o casi siempre'];

        return [
            [
                'slug' => 'inteligencia-emocional',
                'title' => ['es' => 'Test de Inteligencia Emocional', 'en' => 'Emotional Intelligence Test', 'pt' => 'Teste de Inteligencia Emocional'],
                'short_name' => ['es' => 'Int. Emocional', 'en' => 'Emotional IQ', 'pt' => 'Int. Emocional'],
                'description' => ['es' => 'Evalua la capacidad para reconocer, comprender y gestionar emociones propias y ajenas.'],
                'category' => 'bienestar',
                'client_types' => ['personal', 'familiar', 'grupal', 'empresa'],
                'time_limit_minutes' => 15,
                'questions' => [
                    $this->question(1, '¿Con que frecuencia puedes identificar exactamente como te sientes en situaciones emocionales?', $likert, 'autoconciencia'),
                    $this->question(2, '¿Que tan bien puedes reconocer las emociones de otras personas observando sus expresiones?', ['No muy bien', 'Con dificultad', 'Moderadamente bien', 'Muy bien', 'Excepcionalmente bien'], 'empatia'),
                    $this->question(3, 'Cuando enfrentas una situacion estresante, ¿como manejas tus emociones?', ['Me abruman completamente', 'Me cuesta controlarlas', 'Puedo manejarlas con esfuerzo', 'Las manejo bien la mayoria del tiempo', 'Las controlo efectivamente'], 'regulacion'),
                    $this->question(4, '¿Que tan efectivo eres para motivarte cuando enfrentas desafios?', ['Nada efectivo', 'Poco efectivo', 'Moderadamente efectivo', 'Muy efectivo', 'Extremadamente efectivo'], 'automotivacion'),
                    $this->question(5, '¿Con que facilidad puedes comprender la perspectiva emocional de otra persona?', ['Muy dificil', 'Algo dificil', 'Ni facil ni dificil', 'Bastante facil', 'Muy facil'], 'empatia'),
                ],
            ],
            [
                'slug' => 'resiliencia',
                'title' => ['es' => 'Test de Resiliencia', 'en' => 'Resilience Test', 'pt' => 'Teste de Resiliencia'],
                'short_name' => ['es' => 'Resiliencia', 'en' => 'Resilience', 'pt' => 'Resiliencia'],
                'description' => ['es' => 'Mide la capacidad para afrontar adversidades y recuperarse de situaciones dificiles.'],
                'category' => 'bienestar',
                'client_types' => ['personal', 'familiar', 'grupal', 'empresa'],
                'time_limit_minutes' => 12,
                'questions' => [
                    $this->question(1, '¿Como reaccionas cuando enfrentas un contratiempo importante?', ['Me rindo facilmente', 'Me cuesta recuperarme', 'Eventualmente me recupero', 'Me recupero relativamente rapido', 'Me adapto y crezco'], 'adaptacion'),
                    $this->question(2, '¿Que tan bien mantienes una perspectiva positiva durante momentos dificiles?', ['No puedo mantenerla', 'Con mucha dificultad', 'A veces puedo', 'Generalmente puedo', 'Siempre mantengo actitud positiva'], 'optimismo'),
                    $this->question(3, '¿Como describes tu capacidad para aprender de los fracasos?', ['No aprendo de ellos', 'Raramente extraigo lecciones', 'A veces aprendo algo', 'Generalmente aprendo', 'Los convierto en crecimiento'], 'aprendizaje'),
                    $this->question(4, '¿Que tan flexible eres cuando debes adaptarte a cambios inesperados?', ['Nada flexible', 'Poco flexible', 'Moderadamente flexible', 'Bastante flexible', 'Muy flexible'], 'flexibilidad'),
                    $this->question(5, '¿Tienes una red de apoyo a la que recurrir en momentos dificiles?', ['No tengo apoyo', 'Tengo muy poco apoyo', 'Tengo algo de apoyo', 'Tengo buen apoyo', 'Tengo una red muy solida'], 'apoyo'),
                ],
            ],
            [
                'slug' => 'liderazgo',
                'title' => ['es' => 'Test de Liderazgo', 'en' => 'Leadership Test', 'pt' => 'Teste de Lideranca'],
                'short_name' => ['es' => 'Liderazgo', 'en' => 'Leadership', 'pt' => 'Lideranca'],
                'description' => ['es' => 'Identifica estilo de liderazgo y areas de desarrollo.'],
                'category' => 'profesional',
                'client_types' => ['grupal', 'empresa'],
                'time_limit_minutes' => 15,
                'questions' => [
                    $this->question(1, '¿Como te describes liderando un equipo?', ['No me siento comodo', 'Prefiero evitarlo', 'Puedo hacerlo si es necesario', 'Me siento confiado', 'Es mi fortaleza natural'], 'liderazgo'),
                    $this->question(2, '¿Que tan efectivo eres para inspirar a otros hacia una vision compartida?', ['Nada efectivo', 'Poco efectivo', 'Moderadamente efectivo', 'Muy efectivo', 'Extremadamente efectivo'], 'vision'),
                    $this->question(3, '¿Como manejas los conflictos dentro de un equipo?', ['Los evito', 'Me cuesta resolverlos', 'Los manejo cuando es necesario', 'Los resuelvo efectivamente', 'Los transformo en aprendizaje'], 'conflictos'),
                    $this->question(4, '¿Que tan bien delegas responsabilidades?', ['No delego', 'Delego con dificultad', 'Delego moderadamente', 'Delego efectivamente', 'Delego estrategicamente'], 'delegacion'),
                    $this->question(5, '¿Como tomas decisiones dificiles bajo presion?', ['Me paralizo', 'Me cuesta decidir', 'Decido con tiempo', 'Decido con confianza', 'Tomo decisiones estrategicas'], 'decision'),
                ],
            ],
            [
                'slug' => 'estres-burnout',
                'title' => ['es' => 'Test de Estres y Burnout', 'en' => 'Stress and Burnout Test', 'pt' => 'Teste de Estresse e Burnout'],
                'short_name' => ['es' => 'Estres/Burnout', 'en' => 'Stress/Burnout', 'pt' => 'Estresse/Burnout'],
                'description' => ['es' => 'Detecta niveles de estres laboral y signos de agotamiento.'],
                'category' => 'bienestar',
                'client_types' => ['personal', 'familiar', 'grupal', 'empresa'],
                'time_limit_minutes' => 12,
                'questions' => [
                    $this->question(1, '¿Con que frecuencia te sientes agotado al final del dia de trabajo?', ['Nunca', 'Raramente', 'A veces', 'Frecuentemente', 'Siempre'], 'agotamiento'),
                    $this->question(2, '¿Que tan dificil resulta desconectarte del trabajo fuera del horario laboral?', ['Muy facil', 'Facil', 'Ni facil ni dificil', 'Dificil', 'Muy dificil'], 'desconexion'),
                    $this->question(3, '¿Experimentas sintomas fisicos relacionados con el estres laboral?', ['Nunca', 'Raramente', 'Ocasionalmente', 'Frecuentemente', 'Constantemente'], 'somatizacion'),
                    $this->question(4, '¿Como calificas tu energia general durante la jornada laboral?', ['Muy bajo', 'Bajo', 'Moderado', 'Alto', 'Muy alto'], 'energia'),
                    $this->question(5, '¿Sientes que tu trabajo actual te permite crecer profesionalmente?', ['Nada', 'Poco', 'Moderadamente', 'En gran medida', 'Totalmente'], 'crecimiento'),
                ],
            ],
            [
                'slug' => 'motivacion',
                'title' => ['es' => 'Test de Motivacion', 'en' => 'Motivation Test', 'pt' => 'Teste de Motivacao'],
                'short_name' => ['es' => 'Motivacion', 'en' => 'Motivation', 'pt' => 'Motivacao'],
                'description' => ['es' => 'Identifica impulsores motivacionales y claridad de objetivos.'],
                'category' => 'desarrollo',
                'client_types' => ['personal', 'familiar', 'grupal', 'empresa'],
                'time_limit_minutes' => 12,
                'questions' => [
                    $this->question(1, '¿Que factor te motiva mas en tu trabajo diario?', ['Seguridad financiera', 'Reconocimiento', 'Autonomia', 'Impacto social', 'Crecimiento y aprendizaje'], 'motivador'),
                    $this->question(2, '¿Que tan claros tienes tus objetivos personales y profesionales?', ['Nada claros', 'Poco claros', 'Moderadamente claros', 'Muy claros', 'Definidos con plan'], 'claridad'),
                    $this->question(3, '¿Como describes tu compromiso con tus metas actuales?', ['Muy bajo', 'Bajo', 'Moderado', 'Alto', 'Muy alto y constante'], 'compromiso'),
                    $this->question(4, '¿Que tan persistente eres ante obstaculos?', ['Me rindo facilmente', 'Poco persistente', 'Moderadamente persistente', 'Muy persistente', 'Extremadamente persistente'], 'persistencia'),
                    $this->question(5, '¿Que factor consideras mas importante para tu satisfaccion laboral?', ['Salario competitivo', 'Buen ambiente', 'Proposito', 'Desafios intelectuales', 'Balance vida-trabajo'], 'satisfaccion'),
                ],
            ],
            [
                'slug' => 'clima-organizacional',
                'title' => ['es' => 'Test de Clima Organizacional', 'en' => 'Organizational Climate Test', 'pt' => 'Teste de Clima Organizacional'],
                'short_name' => ['es' => 'Clima Org.', 'en' => 'Org. Climate', 'pt' => 'Clima Org.'],
                'description' => ['es' => 'Evalua la percepcion del ambiente laboral en la organizacion.'],
                'category' => 'empresa',
                'client_types' => ['empresa'],
                'time_limit_minutes' => 18,
                'questions' => [
                    $this->question(1, '¿Como describes la calidad de la comunicacion en tu organizacion?', ['Muy deficiente', 'Deficiente', 'Aceptable', 'Buena', 'Excelente'], 'comunicacion'),
                    $this->question(2, '¿Que tan valorado y reconocido te sientes en tu trabajo?', ['Nada valorado', 'Poco valorado', 'Moderadamente valorado', 'Muy valorado', 'Extremadamente valorado'], 'reconocimiento'),
                    $this->question(3, '¿Como calificas la cultura organizacional?', ['Muy negativa', 'Negativa', 'Neutra', 'Positiva', 'Muy positiva'], 'cultura'),
                    $this->question(4, '¿Que tan efectivo consideras el liderazgo en tu organizacion?', ['Nada efectivo', 'Poco efectivo', 'Moderadamente efectivo', 'Muy efectivo', 'Extremadamente efectivo'], 'liderazgo'),
                    $this->question(5, '¿Recomendarias tu organizacion como un excelente lugar para trabajar?', ['Definitivamente no', 'Probablemente no', 'Quizas', 'Probablemente si', 'Definitivamente si'], 'recomendacion'),
                ],
            ],
            [
                'slug' => 'cohesion-familiar',
                'title' => ['es' => 'Test de Cohesion Familiar', 'en' => 'Family Cohesion Test', 'pt' => 'Teste de Coesao Familiar'],
                'short_name' => ['es' => 'Cohesion Familiar', 'en' => 'Family Cohesion', 'pt' => 'Coesao Familiar'],
                'description' => ['es' => 'Analiza la calidad de las relaciones dentro del nucleo familiar.'],
                'category' => 'familiar',
                'client_types' => ['familiar'],
                'time_limit_minutes' => 15,
                'questions' => [
                    $this->question(1, '¿Como describes la comunicacion dentro de tu familia?', ['Muy deficiente', 'Deficiente', 'Aceptable', 'Buena', 'Excelente y abierta'], 'comunicacion'),
                    $this->question(2, '¿Con que frecuencia comparten actividades significativas?', ['Nunca', 'Raramente', 'A veces', 'Frecuentemente', 'Siempre que es posible'], 'conexion'),
                    $this->question(3, '¿Que tan apoyados se sienten emocionalmente los miembros de tu familia?', ['Nada apoyados', 'Poco apoyados', 'Moderadamente apoyados', 'Muy apoyados', 'Totalmente apoyados'], 'apoyo'),
                    $this->question(4, '¿Como se resuelven los conflictos familiares?', ['No se resuelven', 'Con mucha dificultad', 'Eventualmente se resuelven', 'Efectivamente', 'Constructivamente'], 'conflictos'),
                    $this->question(5, '¿Que tan unida sientes a tu familia como nucleo?', ['Nada unida', 'Poco unida', 'Moderadamente unida', 'Muy unida', 'Extremadamente unida'], 'cohesion'),
                ],
            ],
            [
                'slug' => 'toma-decisiones',
                'title' => ['es' => 'Test de Toma de Decisiones', 'en' => 'Decision Making Test', 'pt' => 'Teste de Tomada de Decisoes'],
                'short_name' => ['es' => 'Toma Decisiones', 'en' => 'Decision Making', 'pt' => 'Decisoes'],
                'description' => ['es' => 'Evalua estilo y efectividad en procesos decisionales.'],
                'category' => 'profesional',
                'client_types' => ['grupal', 'empresa'],
                'time_limit_minutes' => 15,
                'questions' => [
                    $this->question(1, '¿Como describes tu estilo al tomar decisiones importantes?', ['Impulsivo', 'Poca reflexion', 'Balance basico', 'Analitico', 'Analisis profundo'], 'estilo'),
                    $this->question(2, '¿Que tan confiado te sientes con tus decisiones?', ['Nada confiado', 'Poco confiado', 'Moderadamente confiado', 'Muy confiado', 'Totalmente confiado'], 'confianza'),
                    $this->question(3, '¿Consideras multiples perspectivas antes de decidir?', ['Nunca', 'Raramente', 'A veces', 'Frecuentemente', 'Siempre'], 'perspectivas'),
                    $this->question(4, '¿Como manejas la incertidumbre al decidir?', ['Me paraliza', 'Me incomoda mucho', 'La manejo con esfuerzo', 'La manejo bien', 'La integro efectivamente'], 'incertidumbre'),
                    $this->question(5, '¿Que tan efectivo eres evaluando consecuencias a largo plazo?', ['Nada efectivo', 'Poco efectivo', 'Moderadamente efectivo', 'Muy efectivo', 'Extremadamente efectivo'], 'consecuencias'),
                ],
            ],
            [
                'slug' => 'creatividad',
                'title' => ['es' => 'Test de Creatividad e Innovacion', 'en' => 'Creativity and Innovation Test', 'pt' => 'Teste de Criatividade e Inovacao'],
                'short_name' => ['es' => 'Creatividad', 'en' => 'Creativity', 'pt' => 'Criatividade'],
                'description' => ['es' => 'Mide capacidad para generar ideas originales e innovadoras.'],
                'category' => 'desarrollo',
                'client_types' => ['personal', 'grupal', 'empresa'],
                'time_limit_minutes' => 12,
                'questions' => [
                    $this->question(1, '¿Con que frecuencia generas ideas nuevas y originales?', ['Nunca', 'Raramente', 'A veces', 'Frecuentemente', 'Constantemente'], 'ideacion'),
                    $this->question(2, '¿Que tan comodo te sientes pensando fuera de marcos convencionales?', ['Muy incomodo', 'Incomodo', 'Neutral', 'Comodo', 'Muy comodo'], 'divergencia'),
                    $this->question(3, '¿Como reaccionas ante problemas que requieren soluciones innovadoras?', ['Me bloqueo', 'Me cuesta', 'Encuentro algo', 'Propongo opciones', 'Genero multiples soluciones'], 'solucion'),
                    $this->question(4, '¿Que tan abierto estas a experimentar nuevos enfoques?', ['Nada abierto', 'Poco abierto', 'Moderadamente abierto', 'Muy abierto', 'Totalmente abierto'], 'experimentacion'),
                    $this->question(5, '¿Con que facilidad conectas ideas aparentemente no relacionadas?', ['Muy dificil', 'Dificil', 'Con esfuerzo', 'Facilmente', 'Con mucha facilidad'], 'conexion'),
                ],
            ],
            [
                'slug' => 'bienestar-integral',
                'title' => ['es' => 'Test de Bienestar Integral', 'en' => 'Integral Wellbeing Test', 'pt' => 'Teste de Bem-estar Integral'],
                'short_name' => ['es' => 'Bienestar', 'en' => 'Wellbeing', 'pt' => 'Bem-estar'],
                'description' => ['es' => 'Evaluacion holistica de bienestar fisico, mental, emocional y social.'],
                'category' => 'bienestar',
                'client_types' => ['personal', 'familiar'],
                'time_limit_minutes' => 12,
                'questions' => [
                    $this->question(1, '¿Como calificas tu bienestar fisico en salud, energia y vitalidad?', ['Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'], 'fisico'),
                    $this->question(2, '¿Que tan satisfecho estas con tu salud mental y equilibrio emocional?', ['Nada satisfecho', 'Poco satisfecho', 'Moderadamente satisfecho', 'Muy satisfecho', 'Totalmente satisfecho'], 'emocional'),
                    $this->question(3, '¿Como describes la calidad de tus relaciones sociales?', ['Muy pobre', 'Pobre', 'Aceptable', 'Buena', 'Excelente'], 'social'),
                    $this->question(4, '¿Que tan equilibrada sientes tu vida personal y profesional?', ['Muy desequilibrada', 'Desequilibrada', 'Algo equilibrada', 'Bien equilibrada', 'Perfectamente equilibrada'], 'balance'),
                    $this->question(5, '¿Como calificas tu felicidad, satisfaccion y proposito de vida?', ['Muy bajo', 'Bajo', 'Moderado', 'Alto', 'Muy alto y pleno'], 'proposito'),
                ],
            ],
        ];
    }

    private function question(int $id, string $text, array $options, string $dimension): array
    {
        return [
            'id' => $id,
            'text' => $text,
            'options' => $options,
            'dimension' => $dimension,
            'reverse_score' => false,
        ];
    }
}
