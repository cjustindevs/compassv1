<?php

namespace Database\Seeders;

use App\Models\SelfHelpResource;
use Illuminate\Database\Seeder;

class SelfHelpResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            [
                'title' => '5-Minute Breathing Exercise',
                'description' => 'A quick box-breathing routine to calm your nervous system in five minutes or less.',
                'category' => 'exercise',
                'content' => "## 5-Minute Breathing Exercise\n\nThis short exercise uses the 4-4-4-4 box breathing technique to settle a racing mind.\n\n## Step 1: Get comfortable\nSit upright with your feet flat on the floor. Rest your hands on your thighs and soften your shoulders.\n\n## Step 2: Breathe in\nInhale slowly through your nose for a count of 4. Feel your belly rise.\n\n## Step 3: Hold\nHold your breath gently for a count of 4. Do not strain.\n\n## Step 4: Breathe out\nExhale slowly through your mouth for a count of 4.\n\n## Step 5: Rest and repeat\nRest at the bottom of the breath for a count of 4, then repeat the cycle. Continue for 5 rounds.\n\n## What to notice\nAfter the exercise, notice how your shoulders feel, how your heart rate slowed down, and how your thoughts feel less urgent. You can use this anytime — before a class, an exam, or a hard conversation.",
                'icon' => '🌬️',
                'duration' => '5 min',
                'difficulty' => 'beginner',
                'tags' => ['anxiety', 'stress', 'calm'],
                'is_featured' => true,
            ],
            [
                'title' => 'Grounding Techniques',
                'description' => 'Use your five senses to pull yourself back to the present during anxiety or panic.',
                'category' => 'exercise',
                'content' => "## Grounding Techniques\n\nWhen anxiety takes over, your mind drifts to the worst-case future. Grounding brings you back to the here and now using your senses.\n\n## The 5-4-3-2-1 method\nTake a slow breath, then:\n\n5 things you can SEE around you. Say them out loud or in your head.\n4 things you can TOUCH. Notice their texture and temperature.\n3 things you can HEAR. Listen for both near and far sounds.\n2 things you can SMELL. If nothing is nearby, think of a favourite scent.\n1 thing you can TASTE. Sip some water or focus on the taste in your mouth.\n\n## Body grounding\nIf 5-4-3-2-1 is not enough, add body grounding: press your feet firmly into the floor and notice the sensation for 30 seconds.\n\n## Making it a habit\nPracticing this when you are calm makes it far more effective when you are not. Try it once a day for a week.",
                'icon' => '🪨',
                'duration' => '8 min',
                'difficulty' => 'beginner',
                'tags' => ['anxiety', 'panic', 'grounding'],
                'is_featured' => true,
            ],
            [
                'title' => 'Understanding Academic Burnout',
                'description' => 'Learn what burnout really is, how it shows up for students, and what you can do about it.',
                'category' => 'article',
                'content' => "## Understanding Academic Burnout\n\nBurnout is more than being tired. It is a state of emotional, mental, and physical exhaustion caused by prolonged stress — and it is extremely common among students.\n\n## What burnout looks like\n- Constant exhaustion that sleep does not fix\n- Feeling detached or cynical about schoolwork\n- A drop in motivation for things you used to enjoy\n- Trouble concentrating, even on small tasks\n- Physical symptoms like headaches or stomach aches\n\n## Why it happens\nBurnout usually builds slowly: endless deadlines, high expectations (your own or others'), poor sleep, and little time for recovery.\n\n## What helps\n- Cut one commitment. You do not need to do everything.\n- Protect your sleep. A 7-9 hour routine beats any all-nighter.\n- Schedule recovery as seriously as classes.\n- Talk about it. Tell a trusted friend, your adviser, or reach out through COMPASS.\n- Learn to say no, including to yourself.\n\n## When to ask for help\nIf exhaustion lasts for weeks or is affecting your health, that is a sign to speak with a professional. Reaching out early is strength, not weakness.",
                'icon' => '🔥',
                'duration' => '12 min',
                'difficulty' => 'intermediate',
                'tags' => ['stress', 'academic', 'burnout'],
                'is_featured' => false,
            ],
            [
                'title' => 'Sleep Hygiene Checklist',
                'description' => 'A practical checklist to build a sleep routine that actually works for students.',
                'category' => 'article',
                'content' => "## Sleep Hygiene Checklist\n\nSleep is when your brain processes everything you learned. Make it a priority with this checklist.\n\n## In the morning\n- Wake up at the same time every day, even on weekends\n- Get 10 minutes of daylight within an hour of waking\n- Do not sleep in more than an hour past your usual time\n\n## During the day\n- Move your body for at least 20 minutes\n- Keep caffeine to the morning, none after 2 PM\n- Take a 20-minute power nap at most, before 3 PM\n\n## In the evening\n- Dim the lights an hour before bed\n- Put the phone away 30 minutes before sleeping\n- Skip heavy meals and alcohol near bedtime\n\n## At bedtime\n- Keep the room cool and dark\n- Use the same wind-down routine every night\n- If you cannot sleep after 20 minutes, get up and do something calm, then try again\n\n## Track it\nTry following the checklist for one week and note how your mood and focus change. Small consistency beats occasional perfection.",
                'icon' => '🌙',
                'duration' => '5 min',
                'difficulty' => 'beginner',
                'tags' => ['sleep', 'health', 'routine'],
                'is_featured' => false,
            ],
            [
                'title' => 'Guided Body Scan Meditation',
                'description' => 'A 10-minute body scan to release tension and drift toward restful sleep.',
                'category' => 'meditation',
                'content' => "## Guided Body Scan Meditation\n\nThis meditation moves your attention slowly through the body, releasing tension as you go. Find a comfortable position — lying down works best.\n\n## Beginning (0-2 minutes)\nClose your eyes and take three deep breaths. Let each exhale be longer than the inhale. Notice the weight of your body against the surface beneath you.\n\n## The scan (2-8 minutes)\nSlowly move your attention:\n\n1. Crown of the head to the forehead. Soften any furrowed muscles.\n2. Jaw and tongue. Let the jaw drop, unclench the teeth.\n3. Neck and shoulders. Let them drop away from your ears.\n4. Arms and hands. Wiggle your fingers and let them relax.\n5. Chest and belly. Feel the gentle rise and fall of each breath.\n6. Hips and legs. Let the weight sink downward.\n7. Feet. Notice your toes and the ground beneath them.\n\n## Ending (8-10 minutes)\nTake one full breath in, and on the exhale bring your awareness back to the room. If you are in bed, let yourself drift. If you are sitting, open your eyes when ready.\n\n## When the mind wanders\nThat is normal. The practice is simply noticing, and coming back, again and again.",
                'icon' => '🧘',
                'duration' => '10 min',
                'difficulty' => 'beginner',
                'tags' => ['meditation', 'relax', 'sleep'],
                'is_featured' => true,
            ],
            [
                'title' => 'Mood Journal',
                'description' => 'A private journaling tool to track how you feel day by day and spot patterns.',
                'category' => 'tool',
                'content' => "## Mood Journal\n\nWriting about how you feel helps you notice patterns that you cannot see from inside the moment.\n\n## How to use this tool\nSet aside 5 minutes at the same time each day. Answer these prompts:\n\n## Daily prompts\n1. What was my dominant mood today? (one word)\n2. How intense was it, from 1 to 10?\n3. What was the most noticeable event of the day?\n4. Did anything make it better or worse? What?\n5. What is one thing I want to carry into tomorrow?\n\n## Weekly review (do this every 7 days)\n- Look back at your dominant moods. What patterns do you see?\n- Which days were hardest, and what tended to happen on them?\n- What have you tried that actually helped?\n\n## Tips\n- Be honest, not polished. This is for you.\n- Do not only record bad days. Capture good ones too.\n- If patterns worry you, bring them to your next COMPASS session or a trusted professional.",
                'icon' => '📓',
                'duration' => 'N/A',
                'difficulty' => 'beginner',
                'tags' => ['journal', 'tracking', 'emotions'],
                'is_featured' => false,
            ],
            [
                'title' => 'Progressive Muscle Relaxation',
                'description' => 'Tense and release each muscle group to melt away physical stress in 15 minutes.',
                'category' => 'exercise',
                'content' => "## Progressive Muscle Relaxation\n\nStress lives in the body. This technique teaches you to notice tension, then let it go, group by group.\n\n## How it works\nFor each muscle group you will tense for 5 seconds, feel the tension, and release for 15 seconds. Notice the difference between tense and relaxed.\n\n## The sequence\n1. Hands: make tight fists, then release\n2. Arms: squeeze your biceps, then let them hang\n3. Shoulders: shrug toward your ears, then drop\n4. Neck: gently push your head back, then relax\n5. Jaw: clench your teeth, then unclench\n6. Eyes and forehead: scrunch your face, then smooth it\n7. Stomach: pull your belly in tight, then let go\n8. Buttocks and thighs: squeeze, then release\n9. Calves: point your toes, then relax\n10. Feet: curl your toes, then release\n\n## Finishing\nLie still for two minutes. Let your whole body feel heavy and warm. If you have any injury, skip that area — you can simply imagine relaxing it instead.\n\n## Best times\nThis works especially well before sleeping or after a long study session.",
                'icon' => '💪',
                'duration' => '15 min',
                'difficulty' => 'intermediate',
                'tags' => ['relaxation', 'stress', 'sleep'],
                'is_featured' => false,
            ],
            [
                'title' => 'Managing Social Anxiety',
                'description' => 'Practical strategies for the nervousness that shows up around people and groups.',
                'category' => 'article',
                'content' => "## Managing Social Anxiety\n\nFeeling nervous before social situations is human. When it starts running your life, it is worth working with directly.\n\n## What social anxiety feels like\nA racing heart before class presentations, replaying conversations afterwards, avoiding group work, worrying what others think. All of it is exhausting.\n\n## What actually helps\n1. Ride the wave. Anxiety peaks then falls. You do not need to be calm — you only need to stay.\n2. Focus outward. Anxiety is inward attention. Practice noticing details in the room instead.\n3. Fact-check your thoughts. \u201CEveryone noticed\u201D is rarely true. Ask: what is the evidence?\n4. Face situations gradually. Start with small exposures — ordering food, asking a question — and build up.\n5. Prepare, do not rehearse. A few talking points are fine; scripting whole conversations feeds the anxiety.\n\n## About avoiding\nAvoidance gives relief now, but grows the fear later. Try the strategy of \u201Capproach with discomfort\u201D — being willing to feel awkward and doing it anyway.\n\n## When to seek help\nIf social anxiety is keeping you from school, friends, or opportunities, consider speaking with a professional. You are allowed to want this to get better.",
                'icon' => '🤝',
                'duration' => '10 min',
                'difficulty' => 'intermediate',
                'tags' => ['anxiety', 'social', 'confidence'],
                'is_featured' => false,
            ],
            [
                'title' => 'Morning Mindfulness Routine',
                'description' => 'Eight mindful minutes to start the day grounded, focused, and on your own terms.',
                'category' => 'meditation',
                'content' => "## Morning Mindfulness Routine\n\nHow you start the day sets its tone. This 8-minute routine is designed to be done right after waking.\n\n## Minute 1 - Wake gently\nBefore touching your phone, pause. Feel the bed, the light, your breath. No task exists yet.\n\n## Minutes 2-3 - Three breaths\nTake three slow breaths. On each inhale, think \u201CI am waking up.\u201D On each exhale, \u201CI am here now.\u201D\n\n## Minutes 4-5 - Body check-in\nScan from your toes to your head. Where do you feel tight? Just notice — no fixing needed.\n\n## Minutes 6-7 - Set one intention\nAsk: \u201CWhat matters most today?\u201D Pick one small thing, not a list. It could be a class, a person, or simply staying kind to yourself.\n\n## Minute 8 - Commit\nSay your intention out loud. Visualize doing it once. Then begin your day.\n\n## Consistency beats length\nEven two mindful minutes beat an elaborate practice you skip. Build the habit first; extend the time later.",
                'icon' => '🌅',
                'duration' => '8 min',
                'difficulty' => 'beginner',
                'tags' => ['mindfulness', 'morning', 'focus'],
                'is_featured' => true,
            ],
            [
                'title' => 'Crisis Coping Strategies',
                'description' => 'Immediate steps for intense moments — and what to do if you need urgent support.',
                'category' => 'article',
                'content' => "## Crisis Coping Strategies\n\nIn intense moments, your thinking brain goes offline. These steps help you get through the next minutes, hour, and day.\n\n## Right now (next 10 minutes)\n- Slow your breathing: four counts in, six counts out\n- Splash cold water on your face or hold something cold\n- Move your body: walk, stretch, or pace\n- Name what you are feeling out loud\n\n## Next hour\n- Remove yourself from anything unsafe\n- Tell one person. A friend, family member, or a COMPASS helper\n- Hydrate and eat something small\n- Stay with simple, small tasks — not big decisions\n\n## Today and tomorrow\n- Be around people, even if you do not talk\n- Avoid alcohol and other numbing shortcuts\n- Write down what you need tomorrow, one item at a time\n- Check in with a COMPASS session or professional\n\n## If you are in danger\nIf you are having thoughts of harming yourself or someone else, this is an emergency. Reach out now — do not wait for a better moment: call a crisis hotline immediately or go to the nearest emergency room. Crisis support is also available through COMPASS's Emergency page. You matter, and getting help is the right decision.",
                'icon' => '🚨',
                'duration' => '6 min',
                'difficulty' => 'intermediate',
                'tags' => ['crisis', 'emergency', 'coping'],
                'is_featured' => false,
            ],
        ];

        foreach ($resources as $resource) {
            SelfHelpResource::updateOrCreate(
                ['title' => $resource['title']],
                $resource
            );
        }
    }
}