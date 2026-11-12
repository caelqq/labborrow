<?php
require 'php/core/bootstrap.php';
require 'php/auth/staff.php';
requireStaff();

$pageTitle = 'Staff';
$currentPage = 'staff';
$mainClass = 'wide staff-page sec-items';
$showStaffMenu = true;

require 'php/core/header.php';
?>
<div class="staff-head">
    <h1>Staff Panel</h1>
    <p>Manage equipment and borrow requests.</p>
</div>

<section class="stats">
    <div class="stat stat-items">
        <span class="stat-num" data-key="items">0</span>
        <span class="stat-label">Items placed</span>
    </div>
    <div class="stat stat-low">
        <span class="stat-num" data-key="no_stock">0</span>
        <span class="stat-label">No Stock items</span>
    </div>
    <div class="stat stat-pending">
        <span class="stat-num" data-key="pending">0</span>
        <span class="stat-label">Pending requests</span>
    </div>
    <div class="stat stat-borrowed">
        <span class="stat-num" data-key="borrowed">0</span>
        <span class="stat-label">Borrowed now</span>
    </div>
    <div class="stat stat-missing">
        <span class="stat-num" data-key="missing">0</span>
        <span class="stat-label">Missing</span>
    </div>
</section>

<section class="staff-section active" id="sec-items" data-csrf="<?= e(csrf_token()) ?>">
    <div class="panel">
        <div class="panel-top">
            <h2>Items</h2>
            <div class="search-box">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input type="search" id="item-search" placeholder="Search items" maxlength="60" autocomplete="off">
            </div>
            <div class="panel-actions">
                <label class="check-inline"><input type="checkbox" id="show-hidden"> Show hidden items</label>
                <button type="button" class="add-btn" id="add-open">+ Add Item</button>
            </div>
        </div>
        <p class="empty-state" id="items-empty" hidden></p>
        <div class="item-grid" id="item-grid"></div>
    </div>
</section>

<section class="staff-section" id="sec-approval">
    <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="pending">Pending</button>
        <button type="button" class="tab-btn" data-tab="approved">Approved</button>
        <button type="button" class="tab-btn" data-tab="using">Using</button>
    </div>
    <div class="panel tab-panel active" id="tab-pending">
        <h2>Pending</h2>
        <p class="empty-state">Nothing here yet.</p>
    </div>
    <div class="panel tab-panel" id="tab-approved">
        <h2>Approved (waiting for pickup)</h2>
        <p class="empty-state">Nothing here yet.</p>
    </div>
    <div class="panel tab-panel" id="tab-using">
        <h2>Using (currently borrowed)</h2>
        <p class="empty-state">Nothing here yet.</p>
    </div>
</section>

<section class="staff-section" id="sec-log">
    <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="daily">Daily</button>
        <button type="button" class="tab-btn" data-tab="borrowed">Borrowed</button>
        <button type="button" class="tab-btn" data-tab="returned">Returned</button>
        <button type="button" class="tab-btn" data-tab="rejected">Rejected</button>
        <button type="button" class="tab-btn" data-tab="missing">Missing</button>
    </div>
    <div class="panel tab-panel active" id="tab-daily">
        <h2>Daily log</h2>
        <p class="empty-state">Nothing here yet.</p>
    </div>
    <div class="panel tab-panel" id="tab-borrowed">
        <h2>Borrowed</h2>
        <p class="empty-state">Nothing here yet.</p>
    </div>
    <div class="panel tab-panel" id="tab-returned">
        <h2>Returned</h2>
        <p class="empty-state">Nothing here yet.</p>
    </div>
    <div class="panel tab-panel" id="tab-rejected">
        <h2>Rejected</h2>
        <p class="empty-state">Nothing here yet.</p>
    </div>
    <div class="panel tab-panel" id="tab-missing">
        <h2>Missing</h2>
        <p class="empty-state">Nothing here yet.</p>
    </div>
</section>

<div class="modal" id="add-modal">
    <div class="modal-box">
        <h2>Add Item</h2>
        <p>List new equipment that students can borrow.</p>
        <form id="add-form" novalidate>
            <div class="field">
                <label for="photo">Photo <span class="required">*</span></label>
                <div class="photo-pick">
                    <img id="photo-preview" alt="Photo preview" hidden>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="error" id="err-photo"></div>
            </div>
            <div class="field">
                <label for="name">Item name <span class="required">*</span></label>
                <input type="text" id="name" name="name" maxlength="60" autocomplete="off">
                <div class="error" id="err-name"></div>
            </div>
            <div class="field">
                <label for="quantity">Quantity <span class="required">*</span></label>
                <input type="text" id="quantity" name="quantity" maxlength="3" inputmode="numeric" autocomplete="off">
                <div class="error" id="err-quantity"></div>
            </div>
            <div class="error" id="add-error"></div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" id="add-cancel">Cancel</button>
                <button type="submit" id="add-submit">Add Item</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="stock-modal">
    <div class="modal-box">
        <h2 id="stock-title">Add stock</h2>
        <p id="stock-text"></p>
        <form id="stock-form" novalidate>
            <div class="field">
                <label for="stock-amount" id="stock-label">Amount <span class="required">*</span></label>
                <input type="text" id="stock-amount" maxlength="3" inputmode="numeric" autocomplete="off">
                <div class="error" id="err-amount"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" id="stock-cancel">Cancel</button>
                <button type="submit" id="stock-submit">Add stock</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="delete-modal">
    <div class="modal-box">
        <h2>Delete this item?</h2>
        <p>Delete <strong id="delete-name"></strong> from the list? Old borrow records are kept.</p>
        <div class="error" id="delete-error"></div>
        <div class="modal-actions">
            <button type="button" class="ghost-btn" id="delete-cancel">Cancel</button>
            <button type="button" class="danger-btn" id="delete-confirm">Delete</button>
        </div>
    </div>
</div>
<script src="js/staff.js"></script>
<?php require 'php/core/footer.php'; ?>