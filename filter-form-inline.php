<?php
// filter-form-inline.php
// Pure PHP, no JS injection. Auto-submits on every change.
// showLoading() gives instant visual feedback before page reload.
$curYear = $curYear ?? (int)date('Y');
?>
<form method="GET" action="listings.php" id="filterForm">
    <input type="hidden" name="sort" value="<?= e($f['sort']) ?>">
    <input type="hidden" name="view" value="<?= e($f['view']) ?>">
    <input type="hidden" name="q"    value="<?= e($f['q']) ?>">

    <!-- MAKE & MODEL -->
    <div class="filter-section">
        <div class="filter-toggle open" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Make &amp; Model</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body open">
            <div class="filter-group">
                <label class="filter-label">Make</label>
                <select name="make" class="filter-select" onchange="showLoading();this.form.submit()">
                    <option value="">Any Make</option>
                    <?php foreach ($makes as $mk): ?>
                    <option value="<?= e($mk['slug']) ?>" <?= $f['make']===$mk['slug']?'selected':'' ?>><?= e($mk['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($f['make'] && $models): ?>
            <div class="filter-group">
                <label class="filter-label">Model</label>
                <select name="model" class="filter-select" onchange="showLoading();this.form.submit()">
                    <option value="">Any Model</option>
                    <?php foreach ($models as $mo): ?>
                    <option value="<?= e($mo['slug']) ?>" <?= $f['model']===$mo['slug']?'selected':'' ?>><?= e($mo['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PRICE -->
    <div class="filter-section">
        <div class="filter-toggle open" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Price (PKR)</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body open">
            <div class="price-range-inputs">
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">Min</label>
                    <input type="number" name="min_price" class="filter-input" placeholder="0"
                           value="<?= $f['min_price']?:'' ?>" min="0" step="100000"
                           onchange="showLoading();this.form.submit()">
                </div>
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">Max</label>
                    <input type="number" name="max_price" class="filter-input" placeholder="Any"
                           value="<?= $f['max_price']?:'' ?>" min="0" step="100000"
                           onchange="showLoading();this.form.submit()">
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:5px;margin-top:6px">
                <?php $pricePresets = [1000000=>'Under 1M',2000000=>'Under 2M',3000000=>'Under 3M',5000000=>'Under 5M',10000000=>'Under 10M'];
                foreach ($pricePresets as $val => $label): ?>
                <label class="filter-check">
                    <input type="radio" name="max_price" value="<?= $val ?>"
                           <?= $f['max_price']==$val?'checked':'' ?>
                           onchange="showLoading();this.form.submit()">
                    <span><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- YEAR -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Year</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-row">
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">From</label>
                    <select name="min_year" class="filter-select" onchange="showLoading();this.form.submit()">
                        <option value="">Any</option>
                        <?php for ($y = $curYear; $y >= 1995; $y--): ?>
                        <option value="<?= $y ?>" <?= $f['min_year']==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">To</label>
                    <select name="max_year" class="filter-select" onchange="showLoading();this.form.submit()">
                        <option value="">Any</option>
                        <?php for ($y = $curYear; $y >= 1995; $y--): ?>
                        <option value="<?= $y ?>" <?= $f['max_year']==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- FUEL & TRANSMISSION -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Fuel &amp; Gearbox</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-group">
                <label class="filter-label">Fuel Type</label>
                <select name="fuel_type" class="filter-select" onchange="showLoading();this.form.submit()">
                    <option value="">Any</option>
                    <option value="petrol"   <?= $f['fuel_type']==='petrol'   ?'selected':''?>>Petrol</option>
                    <option value="diesel"   <?= $f['fuel_type']==='diesel'   ?'selected':''?>>Diesel</option>
                    <option value="hybrid"   <?= $f['fuel_type']==='hybrid'   ?'selected':''?>>Hybrid</option>
                    <option value="electric" <?= $f['fuel_type']==='electric' ?'selected':''?>>Electric</option>
                    <option value="lpg"      <?= $f['fuel_type']==='lpg'      ?'selected':''?>>LPG</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Transmission</label>
                <select name="transmission" class="filter-select" onchange="showLoading();this.form.submit()">
                    <option value="">Any</option>
                    <option value="automatic"      <?= $f['transmission']==='automatic'     ?'selected':''?>>Automatic</option>
                    <option value="manual"         <?= $f['transmission']==='manual'        ?'selected':''?>>Manual</option>
                    <option value="cvt"            <?= $f['transmission']==='cvt'           ?'selected':''?>>CVT</option>
                    <option value="semi_automatic" <?= $f['transmission']==='semi_automatic'?'selected':''?>>Semi-Automatic</option>
                </select>
            </div>
        </div>
    </div>

    <!-- BODY TYPE -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Body Type</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <?php $bodies = ['sedan'=>'<i class="fas fa-car-side"></i> Sedan','hatchback'=>'<i class="fas fa-car"></i> Hatchback','suv'=>'<i class="fas fa-truck-pickup"></i> SUV','pickup'=>'<i class="fas fa-truck-monster"></i> Pickup','van'=>'<i class="fas fa-shuttle-van"></i> Van','wagon'=>'<i class="fas fa-car-rear"></i> Wagon','coupe'=>'<i class="fas fa-car-side"></i> Coupe','minibus'=>'<i class="fas fa-bus"></i> Minibus']; ?>
            <div style="display:flex;flex-direction:column;gap:4px">
                <?php foreach ($bodies as $val => $label): ?>
                <label class="filter-check">
                    <input type="radio" name="body_type" value="<?= $val ?>"
                           <?= $f['body_type']===$val?'checked':'' ?>
                           onchange="showLoading();this.form.submit()">
                    <span><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- LOCATION -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Location</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-group">
                <label class="filter-label">City</label>
                <select name="city" class="filter-select" onchange="showLoading();this.form.submit()">
                    <option value="">All Pakistan</option>
                    <?php foreach ($cities as $c): ?>
                    <option value="<?= e($c['city']) ?>" <?= $f['city']===$c['city']?'selected':'' ?>><?= e($c['city']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- CONDITION & SELLER -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Condition &amp; Seller</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-group">
                <label class="filter-label">Condition</label>
                <select name="condition" class="filter-select" onchange="showLoading();this.form.submit()">
                    <option value="">Any</option>
                    <option value="new"          <?= $f['condition']==='new'          ?'selected':''?>>Brand New</option>
                    <option value="foreign_used" <?= $f['condition']==='foreign_used' ?'selected':''?>>Foreign Used</option>
                    <option value="locally_used" <?= $f['condition']==='locally_used' ?'selected':''?>>Locally Used</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Seller Type</label>
                <select name="seller" class="filter-select" onchange="showLoading();this.form.submit()">
                    <option value="">Any</option>
                    <option value="dealer"  <?= $f['seller']==='dealer'  ?'selected':''?>>Dealers Only</option>
                    <option value="private" <?= $f['seller']==='private' ?'selected':''?>>Private Sellers</option>
                </select>
            </div>
            <label class="filter-check" style="margin-top:6px">
                <input type="checkbox" name="verified" value="1"
                       <?= $f['verified']?'checked':'' ?>
                       onchange="showLoading();this.form.submit()">
                <span><i class="fas fa-check-circle" style="color:var(--blue)"></i> Verified Sellers Only</span>
            </label>
        </div>
    </div>

    <!-- MILEAGE -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Mileage (km)</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-row">
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">Min km</label>
                    <input type="number" name="min_mileage" class="filter-input" placeholder="0"
                           value="<?= $f['min_mileage']?:'' ?>" min="0" step="10000"
                           onchange="showLoading();this.form.submit()">
                </div>
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">Max km</label>
                    <input type="number" name="max_mileage" class="filter-input" placeholder="Any"
                           value="<?= $f['max_mileage']?:'' ?>" min="0" step="10000"
                           onchange="showLoading();this.form.submit()">
                </div>
            </div>
        </div>
    </div>
</form>