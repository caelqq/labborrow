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
        <div class="filter-chips" id="item-filter">
            <button type="button" class="chip active" data-filter="all">All items <span class="chip-count">0</span></button>
            <button type="button" class="chip" data-filter="nostock">No Stock <span class="chip-count">0</span></button>
            <button type="button" class="chip" data-filter="low">Low Stock <span class="chip-count">0</span></button>
            <button type="button" class="chip" data-filter="available">Available <span class="chip-count">0</span></button>
            <button type="button" class="chip" data-filter="hidden">Hidden <span class="chip-count">0</span></button>
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
        <div id="req-pending"></div>
    </div>
    <div class="panel tab-panel" id="tab-approved">
        <h2>Approved (waiting for pickup)</h2>
        <div id="req-approved"></div>
    </div>
    <div class="panel tab-panel" id="tab-using">
        <h2>Using (currently borrowed)</h2>
        <div id="req-using"></div>
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
    <div class="log-search">
        <div class="search-box">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input type="search" id="log-search" placeholder="Search by Log ID or item name" maxlength="60" autocomplete="off">
        </div>
    </div>
    <div class="panel tab-panel active" id="tab-daily">
        <h2>Daily log</h2>
        <div id="log-daily"></div>
    </div>
    <div class="panel tab-panel" id="tab-borrowed">
        <h2>Borrowed</h2>
        <div id="log-borrowed"></div>
    </div>
    <div class="panel tab-panel" id="tab-returned">
        <h2>Returned</h2>
        <div id="log-returned"></div>
    </div>
    <div class="panel tab-panel" id="tab-rejected">
        <h2>Rejected</h2>
        <div id="log-rejected"></div>
    </div>
    <div class="panel tab-panel" id="tab-missing">
        <h2>Missing</h2>
        <div id="log-missing"></div>
    </div>
</section>

<section class="staff-section" id="sec-users">
    <div class="stats user-stats">
        <div class="stat">
            <span class="stat-num" data-user-key="total">0</span>
            <span class="stat-label">Total users</span>
        </div>
        <div class="stat">
            <span class="stat-num" data-user-key="owing">0</span>
            <span class="stat-label">Users with a balance</span>
        </div>
        <div class="stat">
            <span class="stat-num" data-user-key="bad">0</span>
            <span class="stat-label">Users with Bad status</span>
        </div>
    </div>
    <div class="log-search">
        <div class="search-box">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input type="search" id="user-search" placeholder="Search name, username, email or ID" maxlength="60" autocomplete="off">
        </div>
    </div>
    <div class="panel">
        <h2>Users</h2>
        <div id="user-list"></div>
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

<div class="modal" id="req-modal">
    <div class="modal-box">
        <h2 id="req-title">Respond</h2>
        <p id="req-info"></p>

        <div id="req-choose">
            <div class="modal-actions">
                <button type="button" class="ghost-btn" id="req-cancel">Cancel</button>
                <button type="button" class="danger-btn" id="req-reject">Reject</button>
                <button type="button" id="req-approve">Approve</button>
            </div>
        </div>

        <form id="req-approve-form" novalidate hidden>
            <div class="duration-row">
                <div class="field">
                    <label for="req-hours">Hours</label>
                    <input type="text" id="req-hours" maxlength="2" inputmode="numeric" placeholder="0" autocomplete="off">
                </div>
                <div class="field">
                    <label for="req-mins">Minutes</label>
                    <input type="text" id="req-mins" maxlength="2" inputmode="numeric" placeholder="0" autocomplete="off">
                </div>
            </div>
            <p class="small-note">The longest a user can borrow is 24 hours, so type 0 to 23 hours and 0 to 59 minutes.</p>
            <div class="error" id="req-err-time"></div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-back="choose">Back</button>
                <button type="submit">Next</button>
            </div>
        </form>

        <form id="req-reject-form" novalidate hidden>
            <div class="field">
                <label for="req-reason">Why is it rejected? <span class="required">*</span></label>
                <input type="text" id="req-reason" maxlength="100" autocomplete="off">
            </div>
            <div class="error" id="req-err-reason"></div>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-back="choose">Back</button>
                <button type="submit">Next</button>
            </div>
        </form>

        <div id="req-confirm" hidden>
            <p class="confirm-text" id="req-confirm-text"></p>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" id="req-confirm-back">Back</button>
                <button type="button" id="req-confirm-yes">Yes, confirm</button>
            </div>
        </div>
        <div class="error" id="req-error"></div>
    </div>
</div>
<!-- The file time in the address makes the browser load the newest script after every change -->
<script src="js/staff.js?v=<?= (int)filemtime(__DIR__ . '/js/staff.js') ?>"></script>
<script src="js/staff_common.js?v=<?= (int)filemtime(__DIR__ . '/js/staff_common.js') ?>"></script>
<script src="js/staff_logs.js?v=<?= (int)filemtime(__DIR__ . '/js/staff_logs.js') ?>"></script>
<script src="js/staff_approval.js?v=<?= (int)filemtime(__DIR__ . '/js/staff_approval.js') ?>"></script>
<script src="js/staff_users.js?v=<?= (int)filemtime(__DIR__ . '/js/staff_users.js') ?>"></script>
<?php require 'php/core/footer.php'; ?>