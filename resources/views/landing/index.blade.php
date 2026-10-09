<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="COMPASS connects students with supervised peer support and self-help resources.">
    <title>COMPASS - Peer Support, Student Wellness</title>
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
    <link rel="stylesheet" href="{{ asset('css/landing-page.css') }}?v={{ filemtime(public_path('css/landing-page.css')) }}">
</head>
<body class="landing-page">
    <header id="navbar" class="landing-header">
        <div class="landing-container landing-nav">
            <a href="{{ url('/') }}" class="landing-brand" aria-label="COMPASS home">
                <img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS" width="190" height="46">
            </a>
            <nav class="landing-desktop-nav" aria-label="Main navigation">
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#how-it-works">How it works</a>
                <a href="#contact">About</a>
            </nav>
            <div class="landing-nav-actions">
                <a href="{{ route('login') }}" class="landing-sign-in">Log in</a>
                <a href="{{ route('register') }}" class="btn-primary">Get started</a>
                <button id="mobileToggle" type="button" class="landing-menu-toggle" aria-label="Open navigation" aria-controls="mobileMenu" aria-expanded="false">
                    <x-ui-icon name="menu" />
                </button>
            </div>
        </div>
        <nav id="mobileMenu" class="landing-container landing-mobile-menu hidden" aria-label="Mobile navigation">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#how-it-works">How it works</a>
            <a href="#contact">About</a>
            <a href="{{ route('login') }}">Log in</a>
        </nav>
    </header>
    <main id="landing-main">
        <section id="home" class="landing-hero">
            <div class="landing-container landing-hero-grid">
                <div class="landing-hero-copy">
                    <p class="landing-eyebrow">Project Dial-A-Friend <span>Student peer support</span></p>
                    <h1 class="landing-title">A calm place<br>to <span>talk.</span> A safe<br>place to <span>heal.</span></h1>
                    <p class="landing-lead">Some days feel a little heavier. You do not have to carry them on your own. Connect with a trained student peer helper, one conversation at a time.</p>
                    <div class="landing-actions">
                        <a href="{{ route('register') }}" class="btn-primary">Find your support <x-ui-icon name="arrow-right" /></a>
                        <a href="#how-it-works" class="btn-outline">How it works</a>
                    </div>
                    <ul class="landing-trust" aria-label="About peer support">
                        <li>Free for students</li><li>At your own pace</li>
                    </ul>
                </div>
                <figure class="landing-hero-art">
                    <div class="landing-art-frame">
                        <img src="{{ asset('images/compass/peer-support-hero.webp') }}" srcset="{{ asset('images/compass/peer-support-hero-mobile.webp') }} 480w, {{ asset('images/compass/peer-support-hero.webp') }} 960w" sizes="(max-width: 480px) calc(100vw - 32px), (max-width: 768px) 460px, 44vw" alt="Illustration of two students listening and talking together in a peaceful campus garden" width="960" height="960" fetchpriority="high" decoding="async">
                    </div>
                    <figcaption><span>A conversation can be a beginning.</span><p>A place to feel heard, understood, and supported.</p></figcaption>
                </figure>
            </div>
        </section>
        <div class="landing-reassurance" aria-label="What to expect">
            <div class="landing-container landing-reassurance-grid">
                <div><strong>Your space, your nickname.</strong><p>Connect through a pseudonymous account.</p></div>
                <div><strong>Someone to listen.</strong><p>Talk with a trained student peer helper.</p></div>
                <div><strong>Care, with guidance.</strong><p>Peer support under adviser supervision.</p></div>
            </div>
        </div>
        <section id="features" class="landing-section">
            <div class="landing-container">
                <header class="landing-section-heading">
                    <div><p class="landing-eyebrow">A little care goes a long way</p><h2>Support that meets<br>you where you are.</h2></div>
                    <p>Whether you need a conversation, a moment to breathe, or a little direction, there is a place for you here.</p>
                </header>
                <div class="landing-feature-grid">
                    <article class="landing-feature-highlight"><p class="landing-feature-label">A space to be yourself</p><h3>Less pressure.<br>More room to talk.</h3><p>Use your COMPASS nickname and share what you feel comfortable discussing. Start with the concerns that matter to you.</p><a href="{{ route('register') }}" class="landing-text-link">Start a conversation <x-ui-icon name="arrow-right" /></a><div class="landing-feature-word" aria-hidden="true">hello.</div></article>
                    <article><p class="landing-feature-label">Your way of connecting</p><h3>Chat or voice.<br>You choose.</h3><p>Connect in the supported way that feels comfortable for you.</p></article>
                    <article><p class="landing-feature-label">Practical care</p><h3>A moment<br>to breathe.</h3><p>Explore self-help resources, breathing exercises, and grounding tools.</p></article>
                    <article><p class="landing-feature-label">Guidance behind the care</p><h3>Student support.<br>Adviser supervision.</h3><p>Trained peer helpers work under adviser supervision.</p></article>
                    <article><p class="landing-feature-label">When you need more</p><h3>A path to<br>further support.</h3><p>Advisers coordinate professional referrals when appropriate, with existing privacy and consent controls.</p></article>
                </div>
            </div>
        </section>
        <section id="how-it-works" class="landing-section landing-section-soft">
            <div class="landing-container">
                <header class="landing-section-heading">
                    <div><p class="landing-eyebrow">One step at a time</p><h2>Reaching out can<br>start small.</h2></div>
                    <p>Create your account, then take these steps toward peer support. Matching depends on helper availability.</p>
                </header>
                <ol class="landing-steps">
                    <li><span aria-hidden="true">1</span><div><h3>Choose a concern</h3><p>Select what you would like to talk about.</p></div></li>
                    <li><span aria-hidden="true">2</span><div><h3>Get matched</h3><p>Your request is matched when an eligible helper is available.</p></div></li>
                    <li><span aria-hidden="true">3</span><div><h3>Chat or voice</h3><p>Connect in a way that feels comfortable.</p></div></li>
                    <li><span aria-hidden="true">4</span><div><h3>Receive guidance</h3><p>Talk through your concerns and explore coping strategies.</p></div></li>
                    <li><span aria-hidden="true">5</span><div><h3>Feedback &amp; resources</h3><p>Share feedback and explore wellness tools.</p></div></li>
                </ol>
            </div>
        </section>
        <section class="landing-container landing-callout" aria-labelledby="start-heading">
            <div><p class="landing-eyebrow">Whenever you are ready</p><h2 id="start-heading">Your next chapter can<br>begin with a conversation.</h2><p>You do not need to have all the words. Just a place to start.</p></div>
            <div class="landing-callout-actions"><a href="{{ route('register') }}" class="btn-primary">Create an account <x-ui-icon name="arrow-right" /></a><span>Already part of COMPASS? <a href="{{ route('login') }}">Log in</a></span></div>
        </section>
    </main>
    <footer id="contact" class="landing-footer">
        <div class="landing-container">
            <div class="landing-footer-grid">
                <div class="landing-footer-brand"><a href="{{ url('/') }}" aria-label="COMPASS home"><img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS" width="190" height="46"></a><p>A calm place to talk.<br>A safe place to heal.</p><p>Project Dial-A-Friend provides supervised student peer support and wellness resources.</p></div>
                <nav aria-label="Explore COMPASS"><h3>Explore</h3><a href="#features">Features</a><a href="#how-it-works">How it works</a><a href="{{ route('login') }}">Log in</a><a href="{{ route('register') }}">Create an account</a></nav>
                <div class="landing-policies"><h3>Privacy &amp; terms</h3><p>Read the same documents used during registration.</p><details><summary>Privacy Notice</summary><div class="landing-policy-text">@include('partials.privacy-text')</div></details><details><summary>Terms and Condition</summary><div class="landing-policy-text">@include('partials.terms-text')</div></details></div>
            </div>
            <p class="landing-copyright">&copy; {{ now()->year }} COMPASS &middot; Project Dial-A-Friend.</p>
        </div>
    </footer>
    <script>
        (() => {
            const toggle = document.getElementById('mobileToggle');
            const menu = document.getElementById('mobileMenu');
            const setOpen = (open) => {
                menu.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            };
            toggle.addEventListener('click', () => setOpen(menu.classList.contains('hidden')));
            menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !menu.classList.contains('hidden')) { setOpen(false); toggle.focus(); }
            });
            window.addEventListener('resize', () => { if (window.innerWidth >= 1024) setOpen(false); });
        })();
    </script>
    @include('layouts.partials.pwa-banner')
</body>
</html>
