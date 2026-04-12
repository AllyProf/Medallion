@extends('layouts.guest')

@section('title', 'MEDALLION RESTAURANT - Digital Menu')

@section('extra_css')
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@1,600&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-color: #0d0d15;
        --card-bg: #161622;
        --primary-red: #940000;
        --text-white: #ffffff;
        --text-muted: #8e8e93;
        --accent-glow: rgba(148, 0, 0, 0.15);
    }

    body {
        background-color: var(--bg-color) !important;
        color: var(--text-white);
        font-family: 'Poppins', sans-serif !important;
        margin: 0;
        padding: 0;
        width: 100vw;
        overflow-x: hidden !important;
        font-size: 0.85rem;
    }

    .container-fluid { 
        padding: 0 10px; 
        max-width: 100vw;
        overflow-x: hidden;
    }
    .bg-white { background-color: var(--bg-color) !important; }
    .navbar { display: none !important; }
    .hero-header { display: none !important; }

    /* Compact Header */
    .branded-header {
        padding: 25px 15px 10px;
        text-align: center;
        background: var(--bg-color);
    }
    .branded-header h1 {
        font-size: 1.4rem;
        font-weight: 800;
        letter-spacing: 2px;
        color: var(--text-white);
        margin: 0;
        text-transform: uppercase;
        border-bottom: 2px solid var(--primary-red);
        display: inline-block;
        padding-bottom: 5px;
    }

    /* Compact Featured Banner */
    .featured-banner {
        height: 140px;
        background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.7)), url("{{ asset('restoran/img/bg-hero.jpg') }}");
        background-size: cover;
        background-position: center;
        border-radius: 20px;
        margin: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        max-width: calc(100vw - 20px);
    }
    .featured-banner span {
        font-family: 'Playfair Display', serif;
        font-style: italic;
        font-size: 1.8rem;
        color: #fff;
    }

    /* Pro Navigation */
    .pro-nav {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: var(--bg-color);
        padding: 10px 10px 5px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .main-toggle {
        display: flex;
        background: #1c1c2d;
        border-radius: 12px;
        padding: 4px;
        gap: 4px;
        margin-bottom: 10px;
    }

    .toggle-btn {
        flex: 1;
        text-align: center;
        padding: 8px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--text-muted);
        transition: 0.3s;
        cursor: pointer;
    }
    .toggle-btn.active {
        background: var(--primary-red);
        color: white;
    }

    .search-wrapper { margin-bottom: 10px; }
    .search-input {
        background: #1c1c2d;
        border: none;
        border-radius: 10px;
        padding: 10px 15px;
        width: 100%;
        color: white;
        font-size: 0.8rem;
        outline: none;
    }

    .sub-nav-wrapper {
        overflow-x: auto;
        white-space: nowrap;
        padding-bottom: 8px;
        scrollbar-width: none;
    }
    .sub-nav-wrapper::-webkit-scrollbar { display: none; }

    .sub-cat-btn {
        display: inline-block;
        padding: 5px 15px;
        margin-right: 6px;
        background: transparent;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 15px;
        color: var(--text-white);
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 500;
        transition: 0.3s;
    }
    .sub-cat-btn.active {
        border-color: var(--primary-red);
        color: var(--primary-red);
        background: rgba(148, 0, 0, 0.05);
    }

    /* Grid Layout */
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 10px;
    }

    .item-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 20px 10px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.01);
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    .item-card:active { transform: scale(0.96); }

    .icon-circle {
        width: 42px;
        height: 42px;
        background: var(--primary-red);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin: 0 auto 10px;
    }

    .item-name {
        font-weight: 700;
        font-size: 0.85rem;
        margin-bottom: 3px;
        display: block;
        color: var(--text-white);
        line-height: 1.2;
    }

    .item-price {
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--primary-red);
    }

    .cat-section-header { padding: 20px 15px 5px; }
    .cat-section-header h2 {
        font-size: 0.95rem;
        font-weight: 800;
        text-transform: uppercase;
        color: rgba(255,255,255,0.2);
        margin: 0;
        letter-spacing: 1px;
    }

    /* Modal Styling - Premium Dark */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .premium-modal {
        background: #1c1c2d;
        width: 100%;
        max-width: 450px;
        border-radius: 30px;
        padding: 30px;
        position: relative;
        box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        border: 1px solid rgba(255,255,255,0.05);
        animation: modalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes modalIn {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-close {
        position: absolute;
        top: 20px;
        right: 20px;
        color: var(--text-muted);
        font-size: 1.5rem;
        background: rgba(255,255,255,0.05);
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .modal-title {
        font-size: 1.3rem;
        font-weight: 800;
        margin-bottom: 10px;
        padding-right: 40px;
        color: white;
    }

    .modal-description {
        font-size: 0.85rem;
        color: var(--text-muted);
        line-height: 1.6;
        margin-bottom: 25px;
    }

    .extras-title {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--primary-red);
        letter-spacing: 1px;
        margin-bottom: 15px;
        border-bottom: 1px solid rgba(255, 56, 92, 0.1);
        padding-bottom: 5px;
    }

    .extra-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }

    .extra-name { font-weight: 600; font-size: 0.85rem; }
    .extra-price { color: var(--primary-red); font-weight: 600; font-size: 0.8rem; }

    /* Footer */
    .premium-footer {
        position: relative;
        background: linear-gradient(rgba(8, 8, 12, 0.8), rgba(8, 8, 12, 0.95)), url("{{ asset('restoran/img/bg-hero.jpg') }}");
        background-size: cover;
        background-position: center;
        margin-top: 40px;
        padding: 50px 20px 30px;
        border-top-left-radius: 40px;
        border-top-right-radius: 40px;
        text-align: center;
    }

    .footer-thank-you {
        font-family: 'Playfair Display', serif;
        font-style: italic;
        font-size: 1.2rem;
        color: var(--text-white);
        margin-bottom: 30px;
    }

    .footer-contacts {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        max-width: 500px;
        margin: 0 auto 30px;
    }

    .contact-card {
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(5px);
        padding: 15px 5px;
        border-radius: 15px;
        color: var(--text-muted);
    }
    .contact-card i { color: var(--primary-red); font-size: 1rem; margin-bottom: 8px; display: block; }

    .emca-branding {
        padding-top: 25px;
        margin-top: 15px;
        border-top: 1px solid rgba(255,255,255,0.05);
        font-size: 0.75rem;
        color: var(--primary-red);
        font-weight: 700;
    }
    .emca-branding a { color: var(--primary-red); text-decoration: none; }

    .group-container { display: none; }
    .group-container.active { display: block; }
    .d-none { display: none !important; }
</style>
@endsection

@section('content')

<!-- Item Details Modal -->
<div id="detailsModal" class="modal-overlay" onclick="closeModal(event)">
    <div class="premium-modal" onclick="event.stopPropagation()">
        <div class="modal-close" onclick="closeModal()">×</div>
        <div id="modalTitle" class="modal-title">Item Name</div>
        <div id="modalDescription" class="modal-description">Item description goes here...</div>
        
        <div id="extrasSection">
            <div class="extras-title">Extras & Add-ons</div>
            <div id="extrasList">
                <!-- Extras will be injected here -->
            </div>
        </div>
    </div>
</div>

<div class="branded-header">
    <h1>MEDALLION RESTAURANT</h1>
</div>

<!-- Banner -->
<div class="featured-banner">
    <span>Quality Taste, Exceptional Service</span>
</div>

<div class="pro-nav">
    <div class="main-toggle">
        <div class="toggle-btn active" id="btn-food" onclick="navigateMain('food')">FOOD MENU</div>
        <div class="toggle-btn" id="btn-drinks" onclick="navigateMain('drinks')">DRINKS LIST</div>
    </div>
    
    <div class="search-wrapper">
        <input type="text" id="menuSearch" class="search-input" placeholder="Search menu...">
    </div>

    <div class="sub-nav-wrapper" id="sub-nav-food">
        @foreach($foodItems as $category => $items)
            <a href="#food-{{ Str::slug($category) }}" class="sub-cat-btn" onclick="scrollToId('food-{{ Str::slug($category) }}')">{{ $category }}</a>
        @endforeach
    </div>

    <div class="sub-nav-wrapper d-none" id="sub-nav-drinks">
        @foreach($barItems as $category => $items)
            <a href="#drinks-{{ Str::slug($category) }}" class="sub-cat-btn" onclick="scrollToId('drinks-{{ Str::slug($category) }}')">{{ $category }}</a>
        @endforeach
    </div>
</div>

<div class="container-fluid py-2">
    
    {{-- FOOD GROUP --}}
    <div id="food-container" class="group-container active">
        @forelse($foodItems as $category => $items)
            @php
                $catLower = strtolower($category);
                $icon = 'fa-utensils';
                if (str_contains($catLower, 'breakfast')) $icon = 'fa-coffee';
                elseif (str_contains($catLower, 'pizza') || str_contains($catLower, 'burge')) $icon = 'fa-hamburger';
                elseif (str_contains($catLower, 'dessert')) $icon = 'fa-ice-cream';
                elseif (str_contains($catLower, 'soup')) $icon = 'fa-bowl-food';
            @endphp
            <div class="cat-wrapper" id="food-{{ Str::slug($category) }}">
                <div class="cat-section-header">
                    <h2>{{ $category }}</h2>
                </div>
                <div class="menu-grid">
                    @foreach($items as $item)
                        <div class="item-card menu-item-data" 
                             data-name="{{ strtolower($item->name) }}"
                             onclick="showDetails('{{ addslashes($item->name) }}', '{{ addslashes($item->description) }}', '{{ json_encode($item->extras) }}')">
                            <div class="icon-circle"><i class="fa {{ $icon }}"></i></div>
                            <span class="item-name">{{ $item->name }}</span>
                            <span class="item-price">TSh {{ number_format($item->price ?: 0, 0) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-5 text-muted">Food menu items arriving soon...</div>
        @endforelse
    </div>

    {{-- DRINKS GROUP --}}
    <div id="drinks-container" class="group-container">
        @forelse($barItems as $category => $items)
            <div class="cat-wrapper" id="drinks-{{ Str::slug($category) }}">
                <div class="cat-section-header">
                    <h2>{{ $category }}</h2>
                </div>
                <div class="menu-grid">
                    @foreach($items as $item)
                        @php
                            $counterPrice = $item->stockLocations->where('location', 'counter')->first()?->selling_price;
                            $displayPrice = ($counterPrice > 0) ? $counterPrice : ($item->selling_price_per_unit ?: 0);
                        @endphp
                        <div class="item-card menu-item-data" 
                             data-name="{{ strtolower($item->display_name ?: $item->product->name) }}"
                             onclick="showDetails('{{ addslashes($item->display_name ?: $item->product->name) }}', '{{ addslashes($item->product->description ?? '') }}', '[]')">
                            <div class="icon-circle"><i class="fa fa-glass-martini-alt"></i></div>
                            <span class="item-name">{{ $item->display_name ?: $item->product->name }}</span>
                            <span class="item-price">TSh {{ number_format($displayPrice, 0) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-5 text-muted">Drinks list arriving soon...</div>
        @endforelse
    </div>

</div>

<div class="premium-footer">
    <div class="footer-thank-you">Thank you for visiting us! We appreciate your choice and look forward to your next visit.</div>
    <div class="footer-contacts">
        <div class="contact-card"><i class="fa fa-map-marker-alt"></i><span>{{ $owner->address ?? 'Moshi, Tanzania' }}</span></div>
        <div class="contact-card"><i class="fa fa-phone"></i><span>{{ $owner->phone ?? '0710490428' }}</span></div>
        <div class="contact-card"><i class="fa fa-envelope"></i><span>{{ $owner->email ?? 'info@medallion.com' }}</span></div>
        <div class="contact-card"><i class="fa fa-clock"></i><span>Open Daily</span></div>
    </div>
    <div class="emca-branding">Powered By <a href="https://www.emca.tech" target="_blank">EmCa Technologies LTD</a> - www.emca.tech</div>
</div>

<script>
    function showDetails(name, description, extrasJson) {
        document.getElementById('modalTitle').innerText = name;
        document.getElementById('modalDescription').innerText = description || 'Experience the finest taste at Medallion Restaurant.';
        
        const extras = JSON.parse(extrasJson);
        const list = document.getElementById('extrasList');
        const section = document.getElementById('extrasSection');
        
        list.innerHTML = '';
        if (extras && extras.length > 0) {
            section.style.display = 'block';
            extras.forEach(extra => {
                if (extra.is_available) {
                    const div = document.createElement('div');
                    div.className = 'extra-item';
                    div.innerHTML = `
                        <span class="extra-name">${extra.name}</span>
                        <span class="extra-price">+ TSh ${new Number(extra.price).toLocaleString()}</span>
                    `;
                    list.appendChild(div);
                }
            });
        } else {
            section.style.display = 'none';
        }
        
        document.getElementById('detailsModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('detailsModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function navigateMain(type) {
        document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('btn-' + type).classList.add('active');
        document.querySelectorAll('.group-container').forEach(c => c.classList.remove('active'));
        document.getElementById(type + '-container').classList.add('active');
        document.getElementById('sub-nav-food').classList.add('d-none');
        document.getElementById('sub-nav-drinks').classList.add('d-none');
        document.getElementById('sub-nav-' + type).classList.remove('d-none');
        window.scrollTo({ top: 300, behavior: 'smooth' });
    }

    function scrollToId(id) {
        const element = document.getElementById(id);
        if (element) {
            const offset = 140; 
            const bodyRect = document.body.getBoundingClientRect().top;
            const elementRect = element.getBoundingClientRect().top;
            const elementPosition = elementRect - bodyRect;
            const offsetPosition = elementPosition - offset;
            window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
        }
    }

    document.getElementById('menuSearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.menu-item-data').forEach(card => {
            const name = card.getAttribute('data-name');
            card.style.display = name.includes(term) ? 'block' : 'none';
        });
        document.querySelectorAll('.cat-wrapper').forEach(section => {
            const visibleItems = section.querySelectorAll('.menu-item-data[style*="display: block"]').length || 
                                 section.querySelectorAll('.menu-item-data:not([style*="display: none"])').length;
            section.style.display = (visibleItems === 0 && term !== '') ? 'none' : 'block';
        });
    });
</script>
@endsection
