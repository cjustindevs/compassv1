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
                <div>
                    <p class="landing-eyebrow">Project Dial-A-Friend</p>
                    <h1 class="landing-title">A calm place to <span>talk</span>.<br>A safe place to <span>heal</span>.</h1>
                    <p class="landing-lead">COMPASS connects students with trained peer supporters through confidential chat and voice sessions, providing a space for emotional support.</p>
                    <div class="landing-actions">
                        <a href="{{ route('register') }}" class="btn-primary">Get started <x-ui-icon name="arrow-right" /></a>
                        <a href="#features" class="btn-outline">Explore COMPASS</a>
                    </div>
                    <ul class="landing-trust" aria-label="About peer support">
                        <li>Pseudonymous accounts</li><li>Supervised support</li><li>Free for students</li>
                    </ul>
                </div>
                <aside class="landing-support-panel" aria-labelledby="support-panel-title">
                    <p class="landing-eyebrow">At your own pace</p>
                    <h2 id="support-panel-title">A place to start</h2>
                    <p>You can share what is on your mind and find support that fits your needs.</p>
                    <dl class="landing-support-list">
                        <div><dt>Talk with a peer helper</dt><dd>Request a session with a trained student helper under adviser supervision.</dd></div>
                        <div><dt>Explore self-help resources</dt><dd>Find breathing exercises, grounding tools, and other wellness resources.</dd></div>
                        <div><dt>Get further support</dt><dd>Advisers coordinate professional referrals when appropriate.</dd></div>
                    </dl>
                    <a href="#how-it-works" class="landing-text-link">See how it works <x-ui-icon name="arrow-right" /></a>
                </aside>
            </div>
        </section>
        <section id="features" class="landing-section">
            <div class="landing-container">
                <header class="landing-section-heading">
                    <p class="landing-eyebrow">Features</p>
                    <h2>Designed for student wellness</h2>
                    <p>Peer support, practical resources, and supervised care in one place.</p>
                </header>
                <div class="landing-feature-grid">
                    <article><h3>Anonymous conversations</h3><p>Use your COMPASS nickname to share what you feel comfortable discussing.</p></article>
                    <article><h3>Personalized care</h3><p>Request support for the concerns you want to talk about.</p></article>
                    <article><h3>Voice &amp; chat support</h3><p>Choose the supported way of communicating that feels right for you.</p></article>
                    <article><h3>Trained student helpers</h3><p>Peer supporters work under adviser supervision.</p></article>
                    <article><h3>Referral support</h3><p>Advisers coordinate further support for concerns that need professional attention.</p></article>
                    <article><h3>Privacy &amp; consent</h3><p>Review how your information is used before creating your account.</p></article>
                </div>
            </div>
        </section>
        <section id="how-it-works" class="landing-section landing-section-soft">
            <div class="landing-container">
                <header class="landing-section-heading">
                    <p class="landing-eyebrow">How it works</p>
                    <h2>Your path to support</h2>
                    <p>Create your account, then request peer support through the existing COMPASS process.</p>
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
            <div><h2 id="start-heading">You do not have to face challenges alone.</h2><p>Take the first step when you feel ready.</p></div>
            <a href="{{ route('register') }}" class="btn-primary">Create an account <x-ui-icon name="arrow-right" /></a>
        </section>
    </main>
    <footer id="contact" class="landing-footer">
        <div class="landing-container">
            <div class="landing-footer-grid">
                <div><h2>About COMPASS</h2><p>Project Dial-A-Friend provides supervised student peer support and wellness resources.</p></div>
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
