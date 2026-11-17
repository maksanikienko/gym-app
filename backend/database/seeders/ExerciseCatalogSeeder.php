<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Support\Equipment;
use Illuminate\Database\Seeder;

/** Shared exercise catalogue (user_id = null). Safe to re-run. */
class ExerciseCatalogSeeder extends Seeder
{
    /** slug => [name_en, name_ru, muscles (primary first)] */
    public const EXERCISES = [
        // Legs
        'back-squat' => ['Back Squat', 'Приседания со штангой на спине', ['quads', 'glutes', 'back']],
        'goblet-squat' => ['Goblet Squat', 'Гоблет-присед', ['quads', 'glutes']],
        'leg-press' => ['Leg Press', 'Жим ногами', ['quads', 'glutes']],
        'dumbbell-walking-lunge' => ['Dumbbell Walking Lunge', 'Выпады в ходьбе с гантелями', ['quads', 'glutes']],
        'leg-extension' => ['Leg Extension', 'Разгибание ног в тренажёре', ['quads']],
        'single-leg-leg-extension' => ['Single-Leg Leg Extension', 'Разгибание одной ноги в тренажёре', ['quads']],
        'deadlift' => ['Deadlift', 'Становая тяга', ['hamstrings', 'glutes', 'back']],
        'romanian-deadlift' => ['Romanian Deadlift', 'Румынская тяга', ['hamstrings', 'glutes']],
        'glute-ham-raise' => ['Glute Ham Raise', 'Ягодично-подколенный подъём', ['hamstrings', 'glutes']],
        'lying-leg-curl' => ['Lying Leg Curl', 'Сгибание ног лёжа', ['hamstrings']],
        'single-leg-lying-leg-curl' => ['Single-Leg Lying Leg Curl', 'Сгибание одной ноги лёжа', ['hamstrings']],
        'seated-leg-curl' => ['Seated Leg Curl', 'Сгибание ног сидя', ['hamstrings']],
        'leg-curl' => ['Leg Curl', 'Сгибание ног в тренажёре', ['hamstrings']],
        'dumbbell-leg-curl' => ['Dumbbell Leg Curl', 'Сгибание ног с гантелью лёжа', ['hamstrings']],
        'barbell-hip-thrust' => ['Barbell Hip Thrust', 'Ягодичный мост со штангой', ['glutes', 'hamstrings']],
        'dumbbell-single-leg-hip-thrust' => ['Dumbbell Single-Leg Hip Thrust', 'Ягодичный мост на одной ноге с гантелью', ['glutes']],
        'machine-seated-hip-abduction' => ['Machine Seated Hip Abduction', 'Разведение ног в тренажёре сидя', ['glutes']],
        'standing-cable-hip-abduction' => ['Standing Cable Hip Abduction', 'Отведение ноги в кроссовере стоя', ['glutes']],
        'back-extension' => ['Back Extension', 'Гиперэкстензия', ['back', 'glutes', 'hamstrings']],
        'standing-calf-raise' => ['Standing Calf Raise', 'Подъём на носки стоя', ['calves']],
        'dumbbell-standing-calf-raise' => ['Dumbbell Standing Calf Raise', 'Подъём на носки стоя с гантелью', ['calves']],
        // Chest
        'barbell-bench-press' => ['Barbell Bench Press', 'Жим штанги лёжа', ['chest', 'triceps', 'shoulders']],
        'close-grip-bench-press' => ['Close-Grip Bench Press', 'Жим штанги лёжа узким хватом', ['triceps', 'chest']],
        'dumbbell-bench-press' => ['Dumbbell Bench Press', 'Жим гантелей лёжа', ['chest', 'triceps']],
        'dumbbell-incline-press' => ['Dumbbell Incline Press', 'Жим гантелей на наклонной скамье', ['chest', 'shoulders', 'triceps']],
        'dumbbell-floor-press' => ['Dumbbell Floor Press', 'Жим гантелей лёжа на полу', ['chest', 'triceps']],
        'decline-dumbbell-press' => ['Decline Dumbbell Press', 'Жим гантелей на скамье с обратным наклоном', ['chest', 'triceps']],
        'machine-chest-press' => ['Machine Chest Press', 'Жим от груди в тренажёре', ['chest', 'triceps']],
        'machine-incline-chest-press' => ['Machine Incline Chest Press', 'Жим в тренажёре под углом вверх', ['chest', 'shoulders']],
        'cable-flye' => ['Cable Flye', 'Сведение рук в кроссовере', ['chest']],
        'dumbbell-flye' => ['Dumbbell Flye', 'Разводка гантелей лёжа', ['chest']],
        'pec-deck' => ['Pec Deck', 'Сведение рук в тренажёре (бабочка)', ['chest']],
        'assisted-dip' => ['Assisted Dip', 'Отжимания на брусьях в гравитроне', ['chest', 'triceps']],
        // Back
        'lat-pulldown' => ['Lat Pulldown', 'Тяга верхнего блока', ['lats', 'biceps']],
        'reverse-grip-lat-pulldown' => ['Reverse Grip Lat Pulldown', 'Тяга верхнего блока обратным хватом', ['lats', 'biceps']],
        'neutral-grip-pulldown' => ['Neutral-Grip Pulldown', 'Тяга верхнего блока нейтральным хватом', ['lats', 'biceps']],
        'single-arm-pulldown' => ['Single-Arm Pulldown', 'Тяга верхнего блока одной рукой', ['lats']],
        'reverse-grip-assisted-pull-up' => ['Reverse Grip Assisted Pull-Up', 'Подтягивания обратным хватом в гравитроне', ['lats', 'biceps']],
        'cable-seated-row' => ['Cable Seated Row', 'Тяга нижнего блока сидя', ['back', 'lats', 'biceps']],
        'chest-supported-t-bar-row' => ['Chest-Supported T-Bar Row', 'Тяга Т-грифа с упором грудью', ['back', 'lats']],
        'chest-supported-dumbbell-row' => ['Chest-Supported Dumbbell Row', 'Тяга гантелей с упором грудью в скамью', ['back', 'lats']],
        'dumbbell-row' => ['Dumbbell Row', 'Тяга гантели одной рукой в наклоне', ['lats', 'back']],
        'barbell-bent-over-row' => ['Barbell Bent Over Row', 'Тяга штанги в наклоне', ['back', 'lats']],
        'seated-face-pull' => ['Seated Face Pull', 'Тяга каната к лицу сидя', ['shoulders', 'back']],
        // Shoulders
        'military-press' => ['Military Press', 'Армейский жим стоя', ['shoulders', 'triceps']],
        'dumbbell-seated-shoulder-press' => ['Dumbbell Seated Shoulder Press', 'Жим гантелей сидя', ['shoulders', 'triceps']],
        'standing-dumbbell-shoulder-press' => ['Standing Dumbbell Shoulder Press', 'Жим гантелей стоя', ['shoulders', 'triceps']],
        'dumbbell-lateral-raise' => ['Dumbbell Lateral Raise', 'Махи гантелями в стороны', ['shoulders']],
        'cable-lateral-raise' => ['Cable Lateral Raise', 'Отведение руки в сторону на блоке', ['shoulders']],
        'dumbbell-bent-over-lateral-raise' => ['Dumbbell Bent Over Lateral Raise', 'Махи гантелями в наклоне', ['shoulders']],
        'reverse-pec-deck' => ['Reverse Pec Deck', 'Обратная бабочка', ['shoulders', 'back']],
        'cable-reverse-flye' => ['Cable Reverse Flye', 'Обратные разведения в кроссовере', ['shoulders', 'back']],
        // Arms
        'dumbbell-supinated-curl' => ['Dumbbell Supinated Curl', 'Сгибание рук с гантелями с супинацией', ['biceps']],
        'ez-bar-curl' => ['EZ-Bar Curl', 'Сгибание рук с EZ-грифом', ['biceps']],
        'hammer-curl' => ['Hammer Curl', 'Молотковые сгибания', ['biceps']],
        'single-arm-cable-curl' => ['Single-Arm Cable Curl', 'Сгибание руки на блоке', ['biceps']],
        'dumbbell-skull-crusher' => ['Dumbbell Skull Crusher', 'Французский жим с гантелями лёжа', ['triceps']],
        'single-arm-rope-tricep-extension' => ['Single-Arm Rope Tricep Extension', 'Разгибание руки на блоке с канатом', ['triceps']],
        'cable-tricep-kickback' => ['Cable Tricep Kickback', 'Отведение руки назад на блоке', ['triceps']],
        // Core
        'crunch' => ['Crunch', 'Скручивания', ['abs']],
        'bicycle-crunch' => ['Bicycle Crunch', 'Велосипед', ['abs']],
        'hanging-leg-raise' => ['Hanging Leg Raise', 'Подъём ног в висе', ['abs']],
        'plank' => ['Plank', 'Планка', ['abs']],
        // Legs (extended)
        'front-squat' => ['Front Squat', 'Фронтальный присед', ['quads', 'glutes']],
        'bulgarian-split-squat' => ['Bulgarian Split Squat', 'Болгарские выпады', ['quads', 'glutes']],
        'dumbbell-step-up' => ['Dumbbell Step-Up', 'Зашагивания на тумбу с гантелями', ['quads', 'glutes']],
        'smith-machine-reverse-lunge' => ['Smith Machine Reverse Lunge', 'Обратные выпады в Смите', ['quads', 'glutes']],
        'single-leg-leg-press' => ['Single-Leg Leg Press', 'Жим ногами одной ногой', ['quads', 'glutes']],
        'deficit-deadlift' => ['Deficit Deadlift', 'Становая тяга с дефицитом', ['hamstrings', 'glutes', 'back']],
        'reset-deadlift' => ['Reset Deadlift', 'Становая тяга с паузой на полу', ['hamstrings', 'glutes', 'back']],
        'stiff-leg-deadlift' => ['Stiff-Leg Deadlift', 'Тяга на прямых ногах', ['hamstrings', 'glutes']],
        'dumbbell-romanian-deadlift' => ['Dumbbell Romanian Deadlift', 'Румынская тяга с гантелями', ['hamstrings', 'glutes']],
        'single-leg-dumbbell-rdl' => ['Single-Leg Dumbbell RDL', 'Румынская тяга на одной ноге', ['hamstrings', 'glutes']],
        'good-morning' => ['Good Morning', 'Наклоны со штангой (гуд морнинг)', ['hamstrings', 'back']],
        'single-leg-seated-leg-curl' => ['Single-Leg Seated Leg Curl', 'Сгибание одной ноги сидя', ['hamstrings']],
        'sliding-leg-curl' => ['Sliding Leg Curl', 'Сгибание ног скольжением', ['hamstrings']],
        'swiss-ball-leg-curl' => ['Swiss Ball Leg Curl', 'Сгибание ног на фитболе', ['hamstrings']],
        'cable-pull-through' => ['Cable Pull-Through', 'Тяга каната между ног', ['glutes', 'hamstrings']],
        'reverse-hyper' => ['Reverse Hyperextension', 'Обратная гиперэкстензия', ['glutes', 'hamstrings']],
        'round-back-hyperextension' => ['Round-Back Hyperextension', 'Гиперэкстензия с округлённой спиной', ['glutes', 'hamstrings']],
        'cable-glute-kickback' => ['Cable Glute Kickback', 'Отведение ноги назад на блоке', ['glutes']],
        'donkey-kickback' => ['Donkey Kickback', 'Отведение ноги назад на четвереньках', ['glutes']],
        'lateral-band-walk' => ['Lateral Band Walk', 'Шаги в сторону с резинкой', ['glutes']],
        'seated-calf-raise' => ['Seated Calf Raise', 'Подъём на носки сидя', ['calves']],
        // Chest (extended)
        'barbell-incline-press' => ['Barbell Incline Press', 'Жим штанги на наклонной скамье', ['chest', 'shoulders', 'triceps']],
        'barbell-floor-press' => ['Barbell Floor Press', 'Жим штанги лёжа на полу', ['chest', 'triceps']],
        'decline-bench-press' => ['Decline Bench Press', 'Жим штанги на скамье с обратным наклоном', ['chest', 'triceps']],
        'close-grip-smith-machine-press' => ['Close-Grip Smith Machine Press', 'Жим узким хватом в Смите', ['triceps', 'chest']],
        'low-to-high-cable-flye' => ['Low-to-High Cable Flye', 'Сведение в кроссовере снизу вверх', ['chest']],
        'push-up' => ['Push-Up', 'Отжимания', ['chest', 'triceps']],
        'dip' => ['Dip', 'Отжимания на брусьях', ['chest', 'triceps']],
        'weighted-dip' => ['Weighted Dip', 'Отжимания на брусьях с отягощением', ['chest', 'triceps']],
        // Back (extended)
        'pull-up' => ['Pull-Up', 'Подтягивания', ['lats', 'biceps']],
        'weighted-pull-up' => ['Weighted Pull-Up', 'Подтягивания с отягощением', ['lats', 'biceps']],
        'wide-grip-pull-up' => ['Wide-Grip Pull-Up', 'Подтягивания широким хватом', ['lats', 'back']],
        'neutral-grip-pull-up' => ['Neutral-Grip Pull-Up', 'Подтягивания нейтральным хватом', ['lats', 'biceps']],
        'chin-up' => ['Chin-Up', 'Подтягивания обратным хватом', ['lats', 'biceps']],
        'assisted-pull-up' => ['Assisted Pull-Up', 'Подтягивания в гравитроне', ['lats', 'biceps']],
        'wide-grip-lat-pulldown' => ['Wide-Grip Lat Pulldown', 'Тяга верхнего блока широким хватом', ['lats', 'back']],
        'isolateral-pulldown' => ['Iso-Lateral Pulldown', 'Тяга сверху в рычажном тренажёре', ['lats']],
        '1-arm-lat-pull-in' => ['Single-Arm Lat Pull-In', 'Приведение руки на блоке одной рукой', ['lats']],
        'cable-pull-over' => ['Cable Pullover', 'Пуловер на блоке', ['lats']],
        'pendlay-row' => ['Pendlay Row', 'Тяга Пендлея', ['back', 'lats']],
        'barbell-supinated-row' => ['Barbell Supinated Row', 'Тяга штанги обратным хватом', ['back', 'lats', 'biceps']],
        'seal-row' => ['Seal Row', 'Тяга лёжа на скамье (сил-роу)', ['back', 'lats']],
        'humble-row' => ['Humble Row', 'Тяга гантелей лёжа на наклонной скамье', ['back', 'lats']],
        'seated-t-bar-row' => ['Seated T-Bar Row', 'Тяга Т-грифа сидя', ['back', 'lats']],
        'chest-supported-machine-row' => ['Chest-Supported Machine Row', 'Тяга в тренажёре с упором грудью', ['back', 'lats']],
        'machine-high-row' => ['Machine High Row', 'Тяга сверху-вниз в тренажёре', ['lats', 'back']],
        'cable-close-grip-row' => ['Cable Close-Grip Row', 'Тяга нижнего блока узким хватом', ['back', 'lats']],
        'cable-seated-elbows-out-row' => ['Cable Seated Elbows-Out Row', 'Тяга нижнего блока с разведёнными локтями', ['back', 'shoulders']],
        'single-arm-cable-row' => ['Single-Arm Cable Row', 'Тяга блока одной рукой', ['lats', 'back']],
        'band-pull-apart' => ['Band Pull-Apart', 'Разведение резинки перед собой', ['shoulders', 'back']],
        'rope-face-pull' => ['Rope Face Pull', 'Тяга каната к лицу стоя', ['shoulders', 'back']],
        'shrug' => ['Shrug', 'Шраги', ['back']],
        'snatch-grip-barbell-shrug' => ['Snatch-Grip Barbell Shrug', 'Шраги со штангой рывковым хватом', ['back']],
        // Shoulders (extended)
        'barbell-push-press' => ['Barbell Push Press', 'Швунг жимовой', ['shoulders', 'triceps']],
        'arnold-press' => ['Arnold Press', 'Жим Арнольда', ['shoulders', 'triceps']],
        'machine-shoulder-press' => ['Machine Shoulder Press', 'Жим вверх в тренажёре', ['shoulders', 'triceps']],
        'machine-lateral-raise' => ['Machine Lateral Raise', 'Отведение рук в тренажёре', ['shoulders']],
        'egyptian-lateral-raise' => ['Egyptian Lateral Raise', 'Египетское отведение на блоке', ['shoulders']],
        'dumbbell-front-raise' => ['Dumbbell Front Raise', 'Подъём гантелей перед собой', ['shoulders']],
        'dumbbell-front-lateral-raise' => ['Dumbbell Front + Lateral Raise', 'Подъём гантелей перед собой и в стороны', ['shoulders']],
        'cable-upright-row' => ['Cable Upright Row', 'Тяга блока к подбородку', ['shoulders', 'back']],
        // Arms (extended)
        'barbell-curl' => ['Barbell Curl', 'Сгибание рук со штангой', ['biceps']],
        'dumbbell-alternating-curl' => ['Dumbbell Alternating Curl', 'Попеременное сгибание рук с гантелями', ['biceps']],
        'dumbbell-pronated-curl' => ['Dumbbell Pronated Curl', 'Сгибание рук с гантелями обратным хватом', ['biceps']],
        'reverse-grip-ez-bar-curl' => ['Reverse-Grip EZ-Bar Curl', 'Сгибание рук с EZ-грифом обратным хватом', ['biceps']],
        'incline-dumbbell-curl' => ['Incline Dumbbell Curl', 'Сгибание рук с гантелями на наклонной скамье', ['biceps']],
        'spider-curl' => ['Spider Curl', 'Сгибание рук «паук»', ['biceps']],
        'preacher-curl' => ['Preacher Curl', 'Сгибание рук на скамье Скотта', ['biceps']],
        'dumbbell-preacher-curl' => ['Dumbbell Preacher Curl', 'Сгибание руки с гантелью на скамье Скотта', ['biceps']],
        'ez-bar-skull-crusher' => ['EZ-Bar Skull Crusher', 'Французский жим с EZ-грифом', ['triceps']],
        'barbell-floor-skull-crusher' => ['Barbell Floor Skull Crusher', 'Французский жим лёжа на полу', ['triceps']],
        'california-press' => ['California Press', 'Калифорнийский жим', ['triceps', 'chest']],
        'tricep-pressdown' => ['Tricep Pressdown', 'Разгибание рук на блоке', ['triceps']],
        'v-bar-pressdown' => ['V-Bar Pressdown', 'Разгибание рук на блоке с V-рукоятью', ['triceps']],
        'rope-triceps-pressdown' => ['Rope Triceps Pressdown', 'Разгибание рук на блоке с канатом', ['triceps']],
        'rope-overhead-triceps-extension' => ['Rope Overhead Triceps Extension', 'Разгибание рук из-за головы с канатом', ['triceps']],
        // Core (extended)
        'cable-crunch' => ['Cable Crunch', 'Скручивания на блоке', ['abs']],
        'weighted-crunch' => ['Weighted Crunch', 'Скручивания с отягощением', ['abs']],
        'ab-wheel-rollout' => ['Ab Wheel Rollout', 'Ролик для пресса', ['abs']],
        // Placeholders for "pick your own weak point" slots
        'weak-point-upper' => ['Weak Point Exercise (Upper Body)', 'Упражнение на отстающую мышцу (верх)', []],
        'weak-point-lower' => ['Weak Point Exercise (Lower Body)', 'Упражнение на отстающую мышцу (низ)', []],
    ];

    /** [exercise, substitute, note_en, note_ru] */
    public const SUBSTITUTIONS = [
        ['back-squat', 'leg-press', 'Add 3×15 back extensions', 'Добавь 3×15 гиперэкстензий'],
        ['back-squat', 'goblet-squat', null, null],
        ['deadlift', 'barbell-hip-thrust', 'Add 3×15 back extensions', 'Добавь 3×15 гиперэкстензий'],
        ['romanian-deadlift', 'glute-ham-raise', null, null],
        ['dumbbell-walking-lunge', 'dumbbell-single-leg-hip-thrust', null, null],
        ['standing-calf-raise', 'dumbbell-standing-calf-raise', null, null],
        ['lying-leg-curl', 'seated-leg-curl', null, null],
        ['lying-leg-curl', 'dumbbell-leg-curl', null, null],
        ['seated-leg-curl', 'lying-leg-curl', null, null],
        ['machine-seated-hip-abduction', 'standing-cable-hip-abduction', null, null],
        ['barbell-bench-press', 'dumbbell-bench-press', null, null],
        ['barbell-bench-press', 'machine-chest-press', null, null],
        ['dumbbell-incline-press', 'machine-incline-chest-press', null, null],
        ['military-press', 'standing-dumbbell-shoulder-press', null, null],
        ['military-press', 'dumbbell-seated-shoulder-press', null, null],
        ['cable-flye', 'dumbbell-flye', null, null],
        ['cable-flye', 'pec-deck', null, null],
        ['pec-deck', 'cable-flye', null, null],
        ['assisted-dip', 'decline-dumbbell-press', null, null],
        ['reverse-grip-lat-pulldown', 'reverse-grip-assisted-pull-up', null, null],
        ['lat-pulldown', 'neutral-grip-pulldown', null, null],
        ['cable-seated-row', 'dumbbell-row', null, null],
        ['chest-supported-t-bar-row', 'chest-supported-dumbbell-row', null, null],
        ['barbell-bent-over-row', 'chest-supported-dumbbell-row', null, null],
        ['dumbbell-lateral-raise', 'cable-lateral-raise', null, null],
        ['cable-lateral-raise', 'dumbbell-lateral-raise', null, null],
        ['reverse-pec-deck', 'cable-reverse-flye', null, null],
        ['seated-face-pull', 'reverse-pec-deck', null, null],
        ['ez-bar-curl', 'dumbbell-supinated-curl', null, null],
        ['dumbbell-skull-crusher', 'single-arm-rope-tricep-extension', null, null],
        ['crunch', 'bicycle-crunch', null, null],
        ['hanging-leg-raise', 'crunch', null, null],
        ['pull-up', 'assisted-pull-up', null, null],
        ['weighted-pull-up', 'pull-up', null, null],
        ['chin-up', 'reverse-grip-lat-pulldown', null, null],
        ['dip', 'assisted-dip', null, null],
        ['weighted-dip', 'dip', null, null],
        ['front-squat', 'goblet-squat', null, null],
        ['pendlay-row', 'barbell-bent-over-row', null, null],
        ['barbell-incline-press', 'dumbbell-incline-press', null, null],
        ['arnold-press', 'dumbbell-seated-shoulder-press', null, null],
        ['cable-crunch', 'crunch', null, null],
        ['barbell-curl', 'ez-bar-curl', null, null],
        ['v-bar-pressdown', 'rope-triceps-pressdown', null, null],
        ['seated-calf-raise', 'standing-calf-raise', null, null],
    ];

    public function run(): void
    {
        foreach (self::EXERCISES as $slug => [$en, $ru, $muscles]) {
            Exercise::updateOrCreate(
                ['user_id' => null, 'slug' => $slug],
                ['name_en' => $en, 'name_ru' => $ru, 'muscles' => $muscles, 'equipment' => Equipment::guess($slug)]
            );
        }

        $ids = Exercise::whereNull('user_id')->pluck('id', 'slug');
        foreach (self::SUBSTITUTIONS as [$from, $to, $noteEn, $noteRu]) {
            Exercise::find($ids[$from])->substitutes()->syncWithoutDetaching([
                $ids[$to] => ['note_en' => $noteEn, 'note_ru' => $noteRu],
            ]);
        }
    }
}
