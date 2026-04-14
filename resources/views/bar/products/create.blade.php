@extends('layouts.dashboard')

@section('title', 'Register Products')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-cube"></i> Smart Product Registration</h1>
    <p>Select from our global library or add custom brands manually</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('bar.products.index') }}">Products</a></li>
    <li class="breadcrumb-item active">Register</li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">
    <!-- 1. Selection Mode -->
    <div class="tile shadow-sm border-0 mb-4" style="border-top: 4px solid #940000 !important;">
        <div class="row align-items-center">
            <div class="col-md-7 border-right">
                <h4 class="tile-title text-primary mb-2"><i class="fa fa-magic"></i> Option A: Bulk Library Load</h4>
                <p class="small text-muted mb-3">Load brands by distributor to keep your inventory un-mixed and easy to receive.</p>
                
                <div class="mb-3">
                    <span class="smallest font-weight-bold text-uppercase text-muted d-block border-bottom pb-1 mb-2">Sodas, Water &amp; Energizers</span>
                    <button type="button" class="btn btn-outline-primary btn-sm m-1 bulk-load-btn" data-brand="soda_water">Soda &amp; Water</button>
                    <button type="button" class="btn btn-outline-primary btn-sm m-1 bulk-load-btn" data-brand="energizers">Energizers</button>
                </div>

                <div class="mb-3">
                    <span class="smallest font-weight-bold text-uppercase text-muted d-block border-bottom pb-1 mb-2">Beer Bottles &amp; Cans</span>
                    <button type="button" class="btn btn-outline-primary btn-sm m-1 bulk-load-btn" data-brand="lager_bottles">Lager Bottles</button>
                    <button type="button" class="btn btn-outline-primary btn-sm m-1 bulk-load-btn" data-brand="beer_cans">Beer Cans</button>
                </div>

                <div class="mb-0">
                    <span class="smallest font-weight-bold text-uppercase text-muted d-block border-bottom pb-1 mb-2">Spirits &amp; Wine Portfolio</span>
                    <button type="button" class="btn btn-outline-primary btn-sm m-1 bulk-load-btn" data-brand="spirits">Spirits (Whisky/Cognac)</button>
                    <button type="button" class="btn btn-outline-primary btn-sm m-1 bulk-load-btn" data-brand="gin_local">Gin &amp; Local Spirits</button>
                    <button type="button" class="btn btn-outline-primary btn-sm m-1 bulk-load-btn" data-brand="wine_champagne">Wine &amp; Champagne</button>
                    <button type="button" class="btn btn-outline-primary btn-sm m-1 bulk-load-btn" data-brand="house_wines">Local / House Wines</button>
                </div>
            </div>
            <div class="col-md-5">
                <h4 class="tile-title text-dark mb-2"><i class="fa fa-pencil"></i> Option B: Custom Brand</h4>
                <p class="small text-muted">Enter a custom brand name and add rows manually.</p>
                <div class="input-group">
                    <input type="text" id="customBrandInput" class="form-control" placeholder="Type Brand Name...">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-dark" id="startCustomBtn">Start Manual</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('bar.products.store') }}" id="productForm" enctype="multipart/form-data">
      @csrf
      
      <!-- Brand & Category (Hidden until selected OR on validation back) -->
      <div id="mainFormContainer" class="{{ old('brand') ? '' : 'd-none' }}">
          <div class="tile shadow-sm border-0 mb-4 bg-light">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-uppercase text-muted">Active Brand Group</label>
                        <input type="text" name="brand" id="activeBrandName" class="form-control form-control-lg border-primary font-weight-bold" value="{{ old('brand') }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-uppercase text-muted">Core Category</label>
                        <select class="form-control form-control-lg border-primary" name="category" id="activeCategory" required>
                          <option value="SODA & WATER" {{ old('category') === 'SODA & WATER' ? 'selected' : '' }}>SODA &amp; WATER</option>
                          <option value="ENERGIZER" {{ old('category') === 'ENERGIZER' ? 'selected' : '' }}>⚡ ENERGIZER</option>
                          <option value="BEERS / LAGER (BOTTLES)" {{ old('category') === 'BEERS / LAGER (BOTTLES)' ? 'selected' : '' }}>🍺 BEERS / LAGER (BOTTLES)</option>
                          <option value="BEER CANS" {{ old('category') === 'BEER CANS' ? 'selected' : '' }}>🍺 BEER CANS</option>
                          <option value="SPIRITS" {{ old('category') === 'SPIRITS' ? 'selected' : '' }}>🥃 SPIRITS</option>
                          <option value="GIN & LOCAL SPIRITS" {{ old('category') === 'GIN & LOCAL SPIRITS' ? 'selected' : '' }}>🍸 GIN &amp; LOCAL SPIRITS</option>
                          <option value="WINE & CHAMPAGNE" {{ old('category') === 'WINE & CHAMPAGNE' ? 'selected' : '' }}>🍷 WINE &amp; CHAMPAGNE</option>
                          <option value="LOCAL / HOUSE WINES" {{ old('category') === 'LOCAL / HOUSE WINES' ? 'selected' : '' }}>🍷 LOCAL / HOUSE WINES</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <button type="button" class="btn btn-sm btn-link text-danger" onclick="location.reload()"><i class="fa fa-refresh"></i> Reset Selection</button>
                </div>
            </div>
          </div>

          <!-- Variants Table -->
          <div class="tile shadow-sm border-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h3 class="tile-title mb-0 text-primary"><i class="fa fa-list mr-2"></i> Registration List</h3>
              <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" id="addVariantRow">
                <i class="fa fa-plus"></i> Add Row
              </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="variantsTable">
                  <thead class="bg-dark text-white text-center">
                    <tr>
                      <th width="50px">PHOTO</th>
                      <th width="25%">VARIANT / FLAVOR NAME *</th>
                      <th width="12%">SIZE/UNIT</th>
                      <th width="18%">PACKAGING</th>
                      <th width="18%">SELL TYPE</th>
                      <th width="14%">CONVERSION</th>
                      <th width="40px"></th>
                    </tr>
                  </thead>
                  <tbody id="variantsBody">
                  </tbody>
                </table>
            </div>

            <div class="tile-footer text-right border-top pt-3 mt-4">
              <a class="btn btn-light btn-lg px-4 mr-2" href="{{ route('bar.products.index') }}">Cancel</a>
              <button class="btn btn-success btn-lg px-5 shadow-sm font-weight-bold" type="submit" style="background-color: #28a745; border-color: #28a745;">
                <i class="fa fa-check-circle mr-2"></i> COMPLETE REGISTRATION
              </button>
            </div>
          </div>
      </div>
    </form>
  </div>
</div>

<style>
  .bulk-load-btn { transition: all 0.2s; border-radius: 20px; font-weight: 600; padding: 6px 15px; }
  .bulk-load-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
  .variant-row:hover { background-color: #f8f9fa; }
  .form-control:focus { box-shadow: none !important; border-color: #940000 !important; }
  .table-sm td { padding: 0.1rem !important; vertical-align: middle; }
  .tile-title { font-size: 1.1rem; font-weight: 700; letter-spacing: 0.5px; }
  .text-primary { color: #940000 !important; }
  .img-preview-container:hover { border-color: #940000 !important; background: #fdf2f2; }
  .smallest { font-size: 0.65rem; }
  .img-preview-container { transition: all 0.2s; }
</style>
@endsection

@section('scripts')
<script>
    // Image Preview Logic
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('variant-img-input')) {
            const file = e.target.files[0];
            const container = e.target.closest('.img-preview-container');
            const preview = container.querySelector('.preview-img');
            const icon = container.querySelector('.fa-camera');
            if (file) {
                const reader = new FileReader();
                reader.onload = function(re) {
                    preview.src = re.target.result;
                    preview.classList.remove('d-none');
                    icon.classList.add('d-none');
                }
                reader.readAsDataURL(file);
            }
        }
    });

    const productLibrary = {
        "soda_water": {
            brand: "Soda & Water",
            category: "SODA & WATER",
            flavors: [
                { name: "Bonite Soda", size: 350, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 24 },
                { name: "M/Water Big", size: 1.5, unit: "L", sell: "bottle", pkg: "Carton", pkg_qty: 12 },
                { name: "M/Water Small", size: 500, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Hill Sparkling Water", size: 750, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 12 },
                { name: "Ceres Juice", size: 200, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Bavaria Chupa", size: 330, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 24 },
                { name: "Bavaria Can", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Baltika", size: 500, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 12 }
            ]
        },
        "energizers": {
            brand: "Energizers",
            category: "ENERGIZER",
            flavors: [
                { name: "Red Bull", size: 250, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Grand Malta", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 }
            ]
        },
        "lager_bottles": {
            brand: "Lager Beer (Bottles)",
            category: "BEERS / LAGER (BOTTLES)",
            flavors: [
                { name: "Castle Lite", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Kilimanjaro Ndogo", size: 300, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Kilimanjaro Kubwa", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Kilimanjaro Lite", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Safari Ndogo", size: 300, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Safari Kubwa", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Serengeti Lager Ndogo", size: 300, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Serengeti Lager Kubwa", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Serengeti Lite", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Serengeti Lemon", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Castle Lager", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Fly Fish", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Guinness Kubwa", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Guinness Smooth", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Smirnoff Black Ice", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Smirnoff Black Guarana", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Smirnoff Black Pineapple", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Heineken", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Windhoek", size: 440, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Brutal Apple Ruby", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Hanson Dry", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Hanson Lite", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Goldberg", size: 500, unit: "ml", sell: "bottle", pkg: "Crate", pkg_qty: 25 },
                { name: "Stella", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Corona", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 }
            ]
        },
        "beer_cans": {
            brand: "Beer Cans",
            category: "BEER CANS",
            flavors: [
                { name: "Kilimanjaro Can", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Castle Lite Can", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Redd's Can", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Safari Can", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Savanna", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Desperado", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 },
                { name: "Flying Fish Can", size: 330, unit: "ml", sell: "bottle", pkg: "Carton", pkg_qty: 24 }
            ]
        },
        "spirits": {
            brand: "Spirits",
            category: "SPIRITS",
            flavors: [
                { name: "Hennessy VSOP 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Hennessy VS 1L", size: 1000, unit: "ml", sell: "mixed", tots: 33 },
                { name: "Hennessy VS 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Hennessy VS 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Martell VSOP", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Martell VS", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Red Label 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Black Label 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Red Label 375ml", size: 375, unit: "ml", sell: "mixed", tots: 12 },
                { name: "Black Label 375ml", size: 375, unit: "ml", sell: "mixed", tots: 12 },
                { name: "Jack Daniel's 1L", size: 1000, unit: "ml", sell: "mixed", tots: 33 },
                { name: "Jack Daniel's 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Jack Daniel's 375ml", size: 375, unit: "ml", sell: "mixed", tots: 12 },
                { name: "Jack Daniel's 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Jack Daniel's Honey 1L", size: 1000, unit: "ml", sell: "mixed", tots: 33 },
                { name: "Jack Daniel's Honey 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Jack Daniel's Honey 375ml", size: 375, unit: "ml", sell: "mixed", tots: 12 },
                { name: "Jack Daniel's Honey 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Ballantine's 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Ballantine's 375ml", size: 375, unit: "ml", sell: "mixed", tots: 12 },
                { name: "Ballantine's 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Singleton 12 yrs", size: 700, unit: "ml", sell: "mixed", tots: 23 },
                { name: "Singleton 15 yrs", size: 700, unit: "ml", sell: "mixed", tots: 23 },
                { name: "Black & White 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Black & White 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Jägermeister 1L", size: 1000, unit: "ml", sell: "mixed", tots: 33 },
                { name: "Jägermeister 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Jägermeister 350ml", size: 350, unit: "ml", sell: "mixed", tots: 11 },
                { name: "Jägermeister 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Absolut Vodka 1L", size: 1000, unit: "ml", sell: "mixed", tots: 33 },
                { name: "Absolut Vodka 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Absolut Vodka 350ml", size: 350, unit: "ml", sell: "mixed", tots: 11 },
                { name: "Absolut Vodka 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Magic Moments Green Apple 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Magic Moments Chocolate 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Jameson 1L", size: 1000, unit: "ml", sell: "mixed", tots: 33 },
                { name: "Jameson 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Jameson 375ml", size: 375, unit: "ml", sell: "mixed", tots: 12 },
                { name: "Jameson 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Jameson Black Barrel 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "J & B 1L", size: 1000, unit: "ml", sell: "mixed", tots: 33 },
                { name: "J & B 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "J & B 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Grants 1L", size: 1000, unit: "ml", sell: "mixed", tots: 33 },
                { name: "Grants 750ml Glass", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Camino Tequila", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Famous Grouse 350ml", size: 350, unit: "ml", sell: "mixed", tots: 11 },
                { name: "Olmeca Tequila 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 }
            ]
        },
        "gin_local": {
            brand: "Gin & Local Spirits",
            category: "GIN & LOCAL SPIRITS",
            flavors: [
                { name: "Gordons 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Gordons 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Konyagi 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Konyagi 500ml", size: 500, unit: "ml", sell: "mixed", tots: 16 },
                { name: "Konyagi 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Hanson Choice 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Hanson Choice 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "Highlife 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "K-Vant 750ml", size: 750, unit: "ml", sell: "mixed", tots: 25 },
                { name: "K-Vant 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" }
            ]
        },
        "wine_champagne": {
            brand: "Wine & Champagne",
            category: "WINE & CHAMPAGNE",
            flavors: [
                { name: "Martin Champagne 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Moët Nectar Imperial", size: 750, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Moët Rosé", size: 750, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Provetto Rosé", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Provetto Brut", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Non-Alcoholic Champagne", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Four Cousins Sweet Red 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Four Cousins White 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Drostdy Hof Red Claret 700ml", size: 700, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Drostdy Hof Red Claret 375ml", size: 375, unit: "ml", sell: "mixed", tots: 3 },
                { name: "Drostdy Hof CRI White 700ml", size: 700, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Drostdy Hof CRI White 375ml", size: 375, unit: "ml", sell: "mixed", tots: 3 },
                { name: "Pearly Bay Dry Red", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Pearly Bay Dry White", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Pearly Bay Sweet White", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Pearly Bay Sweet Red", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Pearly Bay Sweet Rosé", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "KWV Merlot", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "KWV Chardonnay", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Lions Hill Sweet Red", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Lions Hill Dry White", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Lions Hill Dry Red", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Lions Hill Sweet White", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Robertson Sweet Red 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Robertson Sweet White 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Nederburg Merlot 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Calvet Merlot 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 }
            ]
        },
        "house_wines": {
            brand: "Local / House Wines",
            category: "LOCAL / HOUSE WINES",
            flavors: [
                { name: "Spear Wine Image 250ml", size: 250, unit: "ml", sell: "bottle", pkg: "Piece" },
                { name: "Dompo 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Dompo Ndogo", size: 375, unit: "ml", sell: "mixed", tots: 3 },
                { name: "Altar Wine", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Presidential Noble", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "Dodoma Wine", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "TZEE 750ml", size: 750, unit: "ml", sell: "mixed", tots: 5 },
                { name: "TZEE 200ml", size: 200, unit: "ml", sell: "bottle", pkg: "Piece" }
            ]
        },
    };

    let rowCount = 0;

    function addRow(prefilled = null) {
        const body = document.getElementById('variantsBody');
        const index = rowCount;
        
        const tr = document.createElement('tr');
        tr.className = 'variant-row';
        tr.innerHTML = `
            <td class="p-1 text-center align-middle">
                <div class="img-preview-container" style="width: 40px; height: 40px; border: 1px dashed #ccc; border-radius: 4px; position: relative;">
                    <input type="file" name="variants[${index}][image]" class="variant-img-input" style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer;" accept="image/*">
                    <i class="fa fa-camera text-muted" style="margin-top: 12px;"></i>
                    <img src="" class="preview-img d-none" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px; position: absolute; left: 0; top: 0;">
                </div>
            </td>
            <td class="p-1">
                <input type="text" class="form-control border-0 font-weight-bold variant-name-input" name="variants[${index}][name]" 
                       value="${prefilled ? prefilled.name : ''}" placeholder="e.g. 750ml Premium" required style="border-radius:0;">
            </td>
            <td class="p-1">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control border-0 text-center px-1" name="variants[${index}][measurement]" value="${prefilled ? prefilled.size : ''}" placeholder="500" required>
                    <select class="form-control border-0 px-0" name="variants[${index}][unit]" required style="background: #f8f9fa;">
                        <option value="ml" ${prefilled && prefilled.unit === 'ml' ? 'selected' : ''}>ml</option>
                        <option value="L" ${prefilled && prefilled.unit === 'L' ? 'selected' : ''}>L</option>
                        <option value="PCS" ${prefilled && prefilled.unit === 'PCS' ? 'selected' : ''}>PCS</option>
                    </select>
                </div>
            </td>
            <td class="p-1">
                <div class="row no-gutters align-items-center">
                    <div class="col-8">
                        <select class="form-control border-0 packaging-select" name="variants[${index}][packaging]" onchange="window.togglePackageUnits(this)" required>
                          <option value="Piece" ${prefilled && prefilled.pkg === 'Piece' ? 'selected' : ''}>Pc/Bottle</option>
                          <option value="Carton" ${prefilled && prefilled.pkg === 'Carton' ? 'selected' : ''}>Carton</option>
                          <option value="Crate" ${prefilled && prefilled.pkg === 'Crate' ? 'selected' : ''}>Crate</option>
                          <option value="Outer" ${prefilled && prefilled.pkg === 'Outer' ? 'selected' : ''}>Outer</option>
                        </select>
                    </div>
                    <div class="col-4 pkg-units-container ${prefilled && prefilled.pkg_qty > 1 ? '' : 'd-none'}">
                        <input type="number" class="form-control border-0 bg-light text-center p-0 font-weight-bold" name="variants[${index}][items_per_package]" value="${prefilled ? prefilled.pkg_qty : 1}">
                    </div>
                </div>
            </td>
            <td class="p-1">
                <select class="form-control border-0 selling-type-select" name="variants[${index}][selling_type]" onchange="window.toggleTots(this)" required>
                    <option value="bottle" ${prefilled && prefilled.sell === 'bottle' ? 'selected' : ''}>Bottle Only</option>
                    <option value="glass" ${prefilled && prefilled.sell === 'glass' ? 'selected' : ''}>Glass/Tots</option>
                    <option value="mixed" ${prefilled && prefilled.sell === 'mixed' ? 'selected' : ''}>Mixed (Both)</option>
                </select>
            </td>
            <td class="p-1 bg-light text-center align-middle">
                <div class="input-group input-group-sm ${prefilled && prefilled.tots > 0 ? '' : 'd-none'} tots-container">
                    <input type="number" class="form-control border-0 text-center font-weight-bold" name="variants[${index}][total_tots]" value="${prefilled ? prefilled.tots : ''}" placeholder="Qty">
                    <div class="input-group-append"><span class="input-group-text border-0 bg-transparent smallest pr-1">tots</span></div>
                </div>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-outline-danger btn-sm border-0 remove-row" onclick="this.closest('tr').remove()"><i class="fa fa-trash fa-lg"></i></button>
            </td>
        `;
        body.appendChild(tr);
        rowCount++;
    }

    window.togglePackageUnits = function(select) {
        const row = select.closest('tr');
        const container = row.querySelector('.pkg-units-container');
        if (select.value === 'Carton' || select.value === 'Crate' || select.value === 'Outer') {
            container.classList.remove('d-none');
        } else {
            container.classList.add('d-none');
        }
    }

    window.toggleTots = function(select) {
        const row = select.closest('tr');
        const container = row.querySelector('.tots-container');
        if (select.value === 'glass' || select.value === 'mixed') {
            container.classList.remove('d-none');
        } else {
            container.classList.add('d-none');
        }
    }

    // mode selection
    document.querySelectorAll('.bulk-load-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = productLibrary[this.dataset.brand];
            document.getElementById('mainFormContainer').classList.remove('d-none');
            document.getElementById('activeBrandName').value = data.brand;
            document.getElementById('activeCategory').value = data.category;
            
            document.getElementById('variantsBody').innerHTML = '';
            rowCount = 0;
            data.flavors.forEach(f => addRow(f));
            showToast('success', `Loaded ${data.flavors.length} items from ${data.brand} group.`);
        });
    });

    document.getElementById('startCustomBtn').addEventListener('click', function() {
        const customName = document.getElementById('customBrandInput').value;
        if (!customName) {
            showToast('warning', 'Please enter a brand name first.');
            return;
        }
        document.getElementById('mainFormContainer').classList.remove('d-none');
        document.getElementById('activeBrandName').value = customName;
        document.getElementById('variantsBody').innerHTML = '';
        rowCount = 0;
        addRow();
    });

    document.getElementById('addVariantRow').addEventListener('click', () => addRow());



    // Populate old input if exists
    @if(old('variants'))
        @foreach(old('variants') as $v)
            addRow({
                name: "{{ $v['name'] }}",
                size: "{{ $v['measurement'] }}",
                unit: "{{ $v['unit'] }}",
                pkg: "{{ $v['packaging'] }}",
                pkg_qty: "{{ $v['items_per_package'] }}",
                sell: "{{ $v['selling_type'] }}",
                tots: "{{ $v['total_tots'] }}"
            });
        @endforeach
    @endif
</script>
@endsection
