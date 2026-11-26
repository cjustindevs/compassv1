<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="COMPASS, in partnership with Project Dial-A-Friend, connects students with trained psychology student helpers.">
    <title>COMPASS - Project Dial-A-Friend Peer Support</title>
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
    <link rel="stylesheet" href="{{ asset('css/landing-page.css') }}?v={{ filemtime(public_path('css/landing-page.css')) }}">
</head>
<body class="landing-page">
    <header id="navbar" class="landing-header">
        <div class="landing-container landing-nav">
            <x-pdaf-lockup class="landing-brand" />
            <nav class="landing-desktop-nav" aria-label="Main navigation">
                <a href="#home">Home</a>
                <a href="#about">About PDAF</a>
                <a href="#how-it-works">How it works</a>
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
            <a href="#about">About PDAF</a>
            <a href="#how-it-works">How it works</a>
            <a href="{{ route('login') }}">Log in</a>
            <a href="{{ route('register') }}">Get started</a>
        </nav>
    </header>

    <main id="landing-main">
        <section id="home" class="landing-hero">
            <div class="landing-container landing-hero-grid">
                <div class="landing-hero-copy">
                    <p class="landing-est">
                        <img src="{{ asset('images/pdaf/pdaf-seal-160.webp') }}" alt="" width="24" height="24">
                        Project Dial-A-Friend · Since 2021
                    </p>
                    <h1 class="landing-title">You don't have to figure it out <span>alone.</span></h1>
                    <p class="landing-lead">Talk privately with a trained psychology student. Use a nickname, no real name needed.</p>
                    <div class="landing-actions">
                        <a href="{{ route('register') }}" class="btn-primary">Talk to someone now <x-ui-icon name="arrow-right" /></a>
                        <a href="#about" class="btn-outline">Our story</a>
                    </div>
                    <p class="landing-hours">Live helpers: <strong>6:00–11:00 PM, Monday to Saturday</strong></p>
                </div>
                <div class="landing-hero-art" aria-hidden="true">
                    <svg class="landing-ribbon" viewBox="0 0 600 420" fill="none">
                        <defs><linearGradient id="landingRibbon" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8CC13D"/><stop offset=".55" stop-color="#31711D"/><stop offset="1" stop-color="#2BA9C9"/></linearGradient></defs>
                        <path d="M40 360C160 380 210 120 330 110S520 250 590 60" stroke="url(#landingRibbon)" stroke-width="34" stroke-linecap="round" opacity=".18"/>
                    </svg>
                    <picture>
                        <source srcset="{{ asset('images/pdaf/pdaf-seal-512.webp') }}" type="image/webp">
                        <img class="landing-seal" src="{{ asset('images/pdaf/pdaf-seal-512.png') }}" alt="" width="300" height="300">
                    </picture>
                    <div class="landing-bubble landing-bubble--helper"><small>Helper</small>Hi! I'm here to listen. Take your time.</div>
                    <div class="landing-bubble landing-bubble--you"><small>You</small>Thank you. It's been a rough week.</div>
                </div>
            </div>
        </section>

        <section id="about" class="landing-section" aria-labelledby="about-heading">
            <div class="landing-container">
                <div class="landing-about-head">
                    <h2 id="about-heading">A friend on the other end of the line, since 2021.</h2>
                    <p>Project Dial-A-Friend is a student-led peer-support program of the Psychology Department of Divine Word College of Calapan. Trained psychology students listen; faculty advisers supervise every step.</p>
                </div>
                <ol class="landing-timeline">
                    <li><b>2021</b><h3>The program begins</h3><p>The Psychology Department opens a confidential space for students to talk.</p></li>
                    <li><b>Facebook era</b><h3>One message at a time</h3><p>Students wrote to the official page; moderators matched each one to a helper on duty.</p></li>
                    <li><b>Growing</b><h3>More helpers</h3><p>Volunteers from DWCC and partner schools join, trained and supervised by advisers.</p></li>
                    <li><b>2026</b><h3>COMPASS</h3><p>Requests, chat, voice, supervision and referrals in one secure place.</p></li>
                </ol>
                <div class="landing-about-lower">
                    <dl class="landing-stats" aria-label="Project Dial-A-Friend as of 2026">
                        <div><dt>peer-support sessions</dt><dd>48</dd></div>
                        <div><dt>students supported</dt><dd>42</dd></div>
                        <div><dt>trained volunteers</dt><dd>34</dd></div>
                        <div><dt>volunteers from partner schools</dt><dd>4</dd></div>
                    </dl>
                    <blockquote class="landing-quote">
                        <p>“We are here to provide a safe space for you to share your thoughts and feelings, offering a listening ear without any judgment.”</p>
                        <footer>How every PDAF conversation begins</footer>
                    </blockquote>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="landing-section landing-section-soft" aria-labelledby="how-heading">
            <div class="landing-container">
                <h2 id="how-heading" class="landing-how-title">Three steps to a conversation.</h2>
                <ol class="landing-steps landing-steps--three">
                    <li><span aria-hidden="true">1</span><div><h3>Pick a nickname</h3><p>No real name needed.</p></div></li>
                    <li><span aria-hidden="true">2</span><div><h3>Tell us what's on your mind</h3><p>Choose one or more topics, then chat or voice.</p></div></li>
                    <li><span aria-hidden="true">3</span><div><h3>Start talking</h3><p>Your chat opens as soon as a helper is ready.</p></div></li>
                </ol>
            </div>
        </section>

        <section class="landing-container landing-callout" aria-labelledby="start-heading">
            <div><h2 id="start-heading">You don't need all the words.<br>Just a place to start.</h2></div>
            <div class="landing-callout-actions"><a href="{{ route('register') }}" class="btn-primary">Create a nickname <x-ui-icon name="arrow-right" /></a><span>Already have one? <a href="{{ route('login') }}">Log in</a></span></div>
        </section>
    </main>

    <footer id="contact" class="landing-footer">
        <div class="landing-container">
            <div class="landing-footer-grid">
                <div class="landing-footer-brand"><x-pdaf-lockup /><p>Supervised student peer support from the Psychology Department of Divine Word College of Calapan.</p><p>In danger right now? Call <a href="tel:911">911</a> or the NCMH crisis line <a href="tel:1553">1553</a>.</p></div>
                <nav aria-label="Explore COMPASS"><h3>Explore</h3><a href="#about">About PDAF</a><a href="#how-it-works">How it works</a><a href="{{ route('login') }}">Log in</a><a href="{{ route('register') }}">Get started</a></nav>
                <div class="landing-policies"><h3>Privacy &amp; terms</h3><details><summary>Privacy Notice</summary><div class="landing-policy-text">@include('partials.privacy-text')</div></details><details><summary>Terms and Condition</summary><div class="landing-policy-text">@include('partials.terms-text')</div></details></div>
            </div>
            <p class="landing-copyright">&copy; {{ now()->year }} COMPASS &middot; in partnership with Project Dial-A-Friend.</p>
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
