class BreathingExercise {
    constructor(options = {}) {
        this.steps = [
            { name: 'Inhale', duration: 4000, icon: '⬆️', instruction: 'Inhale through your nose' },
            { name: 'Hold', duration: 2000, icon: 'fa-pause', instruction: 'Hold your breath' },
            { name: 'Exhale', duration: 4000, icon: '⬇️', instruction: 'Exhale slowly through your mouth' },
        ];
        this.totalCycles = options.totalCycles ?? 3;
        this.totalSteps = this.steps.length * this.totalCycles;

        this.currentCycle = 0;
        this.currentStepIndex = 0;
        this.isRunning = false;
        this.isComplete = false;
        this.timer = null;
        this.result = null;

        this.$ = (id) => document.getElementById(id);
    }

    start() {
        if (this.isRunning) return;

        this.stop();
        this.currentCycle = 0;
        this.currentStepIndex = 0;
        this.isRunning = true;
        this.isComplete = false;
        this.result = null;

        this.showIntro();
        this.updateProgress(0);

        const circle = this.$('breath-circle');
        if (circle) circle.classList.remove('inhale', 'hold', 'exhale');

        if (this.$('continue-btn')) this.$('continue-btn').disabled = true;
        if (this.$('skip-btn')) {
            this.$('skip-btn').style.display = 'inline-block';
        }
    }

    stop() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        this.isRunning = false;
    }

    showIntro() {
        const intro = this.$('breath-intro');
        const ui = this.$('breath-exercise-ui');
        if (intro) intro.style.display = 'block';
        if (ui) ui.style.display = 'none';
    }

    showExercise() {
        const intro = this.$('breath-intro');
        const ui = this.$('breath-exercise-ui');
        if (intro) intro.style.display = 'none';
        if (ui) ui.style.display = 'block';

        this.showStep();
    }

    showStep() {
        if (!this.isRunning) return;

        if (this.currentStepIndex >= this.steps.length) {
            this.currentStepIndex = 0;
            this.currentCycle++;
            if (this.currentCycle >= this.totalCycles) {
                this.complete();
                return;
            }
        }

        const step = this.steps[this.currentStepIndex];
        this.updateUI(step);

        const completed = (this.currentCycle * this.steps.length) + this.currentStepIndex;
        this.updateProgress((completed / this.totalSteps) * 100);

        this.timer = setTimeout(() => {
            this.currentStepIndex++;
            this.showStep();
        }, step.duration);
    }

    updateUI(step) {
        if (this.$('breath-icon')) this.$('breath-icon').textContent = step.icon;
        if (this.$('breath-text')) this.$('breath-text').textContent = step.name + '...';
        if (this.$('breath-instruction')) this.$('breath-instruction').textContent = step.instruction;
        if (this.$('breath-timer')) this.$('breath-timer').textContent = (step.duration / 1000) + 's';

        const circle = this.$('breath-circle');
        if (circle) {
            circle.classList.remove('inhale', 'hold', 'exhale');
            circle.classList.add(step.name.toLowerCase());
        }

        if (this.$('step-indicator')) {
            this.$('step-indicator').textContent =
                'Step ' + (this.currentStepIndex + 1) + ' of ' + this.steps.length +
                ' · Cycle ' + (this.currentCycle + 1) + ' of ' + this.totalCycles;
        }
    }

    updateProgress(progress) {
        const bar = this.$('breath-progress');
        if (bar) bar.style.width = Math.min(100, Math.max(0, progress)) + '%';
    }

    complete(skipped = false) {
        this.stop();
        this.isComplete = true;
        this.result = skipped ? 'skipped' : 'completed';

        if (this.$('continue-btn')) this.$('continue-btn').disabled = false;
        if (this.$('skip-btn')) this.$('skip-btn').style.display = 'none';
        if (!skipped) this.updateProgress(100);
    }

    skip() {
        this.complete(true);
    }

    reset() {
        this.stop();
        this.isComplete = false;
        this.result = null;

        if (this.$('continue-btn')) this.$('continue-btn').disabled = true;
        if (this.$('skip-btn')) this.$('skip-btn').style.display = 'inline-block';

        if (this.$('breath-progress')) this.$('breath-progress').style.width = '0%';

        const circle = this.$('breath-circle');
        if (circle) circle.classList.remove('inhale', 'hold', 'exhale');

        this.showIntro();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('exercise-modal');
    if (!modal) return;

    const exercise = new BreathingExercise();

    const $ = (id) => document.getElementById(id);

    function openModal() {
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        exercise.start();
        const begin = $('begin-lesson-btn');
        if (begin) begin.focus();
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        exercise.stop();
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        const start = $('start-exercise-btn');
        if (start) start.focus();
    }

    function markSubmitted() {
        const input = document.getElementById('exercise-completed');
        if (input) input.value = exercise.result || 'skipped';

        const status = $('exercise-status');
        if (status) {
            status.textContent = exercise.result === 'skipped' ? '⏭️ Skipped' : ' Completed';
            status.classList.remove('text-gray-400');
            status.classList.add(exercise.result === 'skipped' ? 'text-amber-600' : 'text-green-600');
        }

        const submitBtn = document.querySelector('#readinessForm button[type="submit"]');
        if (submitBtn) submitBtn.disabled = false;

        const bannerBtn = $('start-exercise-btn');
        if (bannerBtn) {
            bannerBtn.disabled = true;
            bannerBtn.classList.add('opacity-60', 'cursor-not-allowed');
        }

        closeModal();
    }

    const startBtn = $('start-exercise-btn');
    if (startBtn) startBtn.addEventListener('click', openModal);

    const beginBtn = $('begin-lesson-btn');
    if (beginBtn) beginBtn.addEventListener('click', () => exercise.showExercise());

    const skipBtn = $('skip-btn');
    if (skipBtn) skipBtn.addEventListener('click', () => exercise.skip());

    const continueBtn = $('continue-btn');
    if (continueBtn) continueBtn.addEventListener('click', markSubmitted);

    const closeBtn = $('close-modal-btn');
    if (closeBtn) closeBtn.addEventListener('click', () => {
        exercise.reset();
        closeModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('active')) {
            exercise.reset();
            closeModal();
        }
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            exercise.reset();
            closeModal();
        }
    });

    window.breathingExercise = exercise;
});
