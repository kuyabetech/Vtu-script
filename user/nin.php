<?php
// user/nin.php - Nin Page
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();
$walletBalance = getUserBalance($userId);

// Get user's wallet ID
$walletId = null;
$stmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($wallet = $result->fetch_assoc()) {
    $walletId = $wallet['id'];
}

$pageTitle = 'Nin';
include '../partials/user_header.php';
?>

<style>
/* ===== RESPONSIVE ADMIN SERVICES STYLES ===== */
/* Save this as assets/css/admin-services.css or add to admin_header.php */

/* CSS Variables */
:root {
    --primary: #6366f1;
    --primary-dark: #4f52e0;
    --primary-light: #818cf8;
    --secondary: #8b5cf6;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --dark: #1f2937;
    --gray: #6b7280;
    --gray-light: #e5e7eb;
    --light: #f9fafb;
    --white: #ffffff;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    --radius: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
    --transition: all 0.3s ease;
    --header-height: 70px;
    --sidebar-width: 280px;
}

/* Reset & Base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: var(--light);
    color: var(--dark);
    line-height: 1.5;
    min-height: 100vh;
}

/* Container - Fully Responsive */
.container-fluid {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

@media (min-width: 640px) {
    .container-fluid {
        padding: 1.25rem;
    }
}

@media (min-width: 768px) {
    .container-fluid {
        padding: 1.5rem;
    }
}

@media (min-width: 1024px) {
    .container-fluid {
        padding: 2rem;
    }
}

/* Content Header - Responsive */
.content-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

@media (min-width: 768px) {
    .content-header {
        margin-bottom: 2rem;
    }
}

.content-header h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--dark);
    font-size: clamp(1.25rem, 4vw, 1.75rem);
    margin: 0;
}

.content-header h2 i {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    padding: 0.75rem;
    border-radius: 50%;
    font-size: clamp(1rem, 3vw, 1.25rem);
}

/* Badge */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 1rem;
    border-radius: 2rem;
    font-size: clamp(0.7rem, 2vw, 0.8rem);
    font-weight: 600;
    white-space: nowrap;
    line-height: 1.2;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

/* Action Buttons - Fully Responsive */
.action-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .action-buttons {
        margin-bottom: 2rem;
    }
}

.action-buttons .btn {
    flex: 1 1 auto;
    min-width: 180px;
}

@media (max-width: 640px) {
    .action-buttons {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .action-buttons .btn {
        width: 100%;
        min-width: 100%;
    }
}

/* Button Styles - Touch Friendly */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-size: clamp(0.875rem, 2.5vw, 0.95rem);
    font-weight: 500;
    border-radius: var(--radius);
    cursor: pointer;
    transition: var(--transition);
    border: none;
    outline: none;
    text-decoration: none;
    white-space: nowrap;
    min-height: 44px;
}

@media (max-width: 640px) {
    .btn {
        padding: 0.75rem 1rem;
    }
}

@media (min-width: 768px) {
    .btn:hover {
        transform: translateY(-2px);
    }
}

.btn:active {
    transform: translateY(0);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: var(--white);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover:not(:disabled) {
    box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
}

.btn-success {
    background: var(--success);
    color: var(--white);
}

.btn-success:hover {
    background: #0d9488;
}

.btn-light {
    background: var(--white);
    color: var(--dark);
    border: 1px solid var(--gray-light);
}

.btn-light:hover {
    background: var(--light);
    border-color: var(--primary);
}

.btn-danger {
    background: var(--danger);
    color: var(--white);
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-small {
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
    min-height: 36px;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Cards - Responsive */
.card {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 1rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--gray-light);
    overflow: hidden;
    transition: var(--transition);
}

@media (min-width: 640px) {
    .card {
        padding: 1.25rem;
    }
}

@media (min-width: 768px) {
    .card {
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--gray-light);
    flex-wrap: wrap;
    gap: 0.75rem;
}

@media (min-width: 768px) {
    .card-header {
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
    }
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: clamp(1rem, 3vw, 1.125rem);
    color: var(--dark);
    margin: 0;
}

.card-header h3 i {
    color: var(--primary);
    font-size: clamp(0.875rem, 2.5vw, 1rem);
}

/* Tables - Fully Responsive with Horizontal Scroll */
.table-responsive {
    overflow-x: auto;
    border-radius: var(--radius);
    margin: 0 -1rem;
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 640px) {
    .table-responsive {
        margin: 0 -0.75rem;
    }
}

@media (min-width: 768px) {
    .table-responsive {
        margin: 0;
    }
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

@media (max-width: 1024px) {
    .data-table {
        min-width: 750px;
    }
}

@media (max-width: 768px) {
    .data-table {
        min-width: 700px;
    }
}

.data-table th {
    background: var(--light);
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    font-size: clamp(0.8rem, 2.5vw, 0.875rem);
    white-space: nowrap;
}

@media (min-width: 768px) {
    .data-table th {
        padding: 1rem;
    }
}

.data-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-light);
    color: var(--dark);
    font-size: clamp(0.8rem, 2.5vw, 0.875rem);
}

@media (min-width: 768px) {
    .data-table td {
        padding: 1rem;
    }
}

.data-table tr:hover {
    background: var(--light);
}

.data-table td code {
    background: var(--light);
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.8rem;
    color: var(--primary);
    font-family: monospace;
}

/* Action Cell */
.actions-cell {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

@media (max-width: 480px) {
    .actions-cell {
        flex-direction: column;
    }
    
    .actions-cell .btn {
        width: 100%;
    }
}

/* Alerts - Responsive */
.alert {
    padding: 0.75rem 1rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
    flex-wrap: wrap;
    font-size: clamp(0.8rem, 2.5vw, 0.875rem);
}

@media (min-width: 768px) {
    .alert {
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
    }
}

@media (max-width: 480px) {
    .alert {
        flex-direction: column;
        text-align: center;
    }
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid var(--success);
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid var(--danger);
}

.alert i {
    font-size: 1.25rem;
}

/* Modal - Fully Responsive */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: 1.25rem;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideUp 0.3s ease;
    box-shadow: var(--shadow-xl);
}

@media (min-width: 640px) {
    .modal-content {
        padding: 1.5rem;
    }
}

@media (min-width: 768px) {
    .modal-content {
        padding: 2rem;
    }
}

@keyframes modalSlideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--gray-light);
    flex-wrap: wrap;
    gap: 0.75rem;
}

@media (min-width: 768px) {
    .modal-header {
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
    }
}

.modal-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: clamp(1rem, 4vw, 1.125rem);
    color: var(--dark);
    margin: 0;
}

.modal-header i {
    color: var(--primary);
    font-size: 1.25rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--gray);
    transition: color 0.3s;
    padding: 0.5rem;
    min-width: 44px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--danger);
}

.modal-body {
    margin-bottom: 1rem;
}

@media (min-width: 768px) {
    .modal-body {
        margin-bottom: 1.25rem;
    }
}

.modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 2px solid var(--gray-light);
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .modal-footer {
        gap: 1rem;
        margin-top: 1.5rem;
        padding-top: 1rem;
    }
}

@media (max-width: 480px) {
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
}

/* Forms - Responsive */
.form-group {
    margin-bottom: 1rem;
}

@media (min-width: 768px) {
    .form-group {
        margin-bottom: 1.25rem;
    }
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--dark);
    font-size: clamp(0.8rem, 2.5vw, 0.875rem);
}

.form-control {
    width: 100%;
    padding: 0.625rem 0.875rem;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius);
    font-size: clamp(0.85rem, 2.5vw, 0.9rem);
    transition: var(--transition);
    background: var(--white);
    min-height: 44px;
}

@media (min-width: 768px) {
    .form-control {
        padding: 0.75rem 1rem;
    }
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

@media (min-width: 640px) {
    .form-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}

@media (min-width: 768px) {
    .form-row {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Color Picker */
.color-picker {
    width: 100%;
    height: 44px;
    padding: 0.25rem;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius);
    cursor: pointer;
}

@media (min-width: 768px) {
    .color-picker {
        height: 48px;
    }
}

/* Icon Preview */
.icon-preview {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: var(--light);
    border-radius: var(--radius);
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .icon-preview {
        padding: 0.75rem;
    }
}

.icon-preview i {
    font-size: 1.25rem;
    color: var(--primary);
}

.icon-preview span {
    font-size: 0.85rem;
    color: var(--gray);
}

/* Categories Grid - Fully Responsive */
.categories-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

@media (min-width: 480px) {
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 768px) {
    .categories-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
}

@media (min-width: 1024px) {
    .categories-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
}

.category-item {
    background: var(--light);
    padding: 0.75rem;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 2px solid transparent;
    transition: var(--transition);
    min-height: 52px;
}

@media (min-width: 768px) {
    .category-item {
        padding: 1rem;
        min-height: 60px;
    }
}

.category-item:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.category-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    flex: 1;
}

@media (min-width: 768px) {
    .category-info {
        gap: 0.75rem;
    }
}

.category-info i {
    font-size: 1rem;
    color: var(--primary);
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .category-info i {
        font-size: 1.125rem;
    }
}

.category-info span {
    font-weight: 500;
    color: var(--dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: clamp(0.8rem, 2.5vw, 0.875rem);
}

.category-count {
    background: var(--white);
    padding: 0.2rem 0.6rem;
    border-radius: 2rem;
    font-size: 0.7rem;
    color: var(--gray);
    white-space: nowrap;
    margin-left: 0.5rem;
}

@media (min-width: 768px) {
    .category-count {
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
    }
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--gray);
}

@media (min-width: 768px) {
    .empty-state {
        padding: 3rem;
    }
}

.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    opacity: 0.5;
}

@media (min-width: 768px) {
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
}

.empty-state p {
    margin-bottom: 1rem;
    font-size: clamp(0.85rem, 2.5vw, 0.95rem);
}

/* Loading Spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Animations */
@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease;
}

/* Touch-friendly improvements */
@media (max-width: 768px) {
    .btn, 
    .modal-close,
    .category-item,
    .form-control {
        cursor: default;
    }
    
    .btn:active,
    .category-item:active {
        opacity: 0.7;
        transform: scale(0.98);
    }
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--light);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--gray);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}

/* Print Styles */
@media print {
    .btn,
    .modal,
    .action-buttons,
    .bottom-nav {
        display: none;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .table-responsive {
        overflow: visible;
    }
}.
/* Custom styles for Nin page */
.content-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.content-header h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--dark);
    font-size: clamp(1.25rem, 4vw, 1.75rem);
}

.content-header h2 i {
    color: #00ff00;
    background: rgba(#00ff00, 0.1);
    padding: 0.75rem;
    border-radius: 50%;
}

.balance-badge {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 2rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.card {
    background: white;
    border-radius: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--gray-light);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-light);
}

.card-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--dark);
}

.card-header i {
    color: #00ff00;
    font-size: 1.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--gray);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: #00ff00;
}

.empty-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--dark);
}

.empty-state p {
    margin-bottom: 1.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 500;
    border-radius: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    outline: none;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
}

.main-content {
    padding-bottom: calc(var(--bottom-nav-height) + 1.5rem);
}

@media (max-width: 768px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="main-content">
    <div class="container">
        <div class="content-header">
            <h2>
                <i class="fas fa-folder"></i>
                <span>Nin</span>
            </h2>
            <div class="balance-badge">
                <i class="fas fa-wallet"></i>
                <span><?php echo format_money($walletBalance); ?></span>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-folder"></i>
                <h3>Nin Services</h3>
            </div>
            <div class="empty-state">
                <i class="fas fa-folder"></i>
                <h3>Coming Soon</h3>
                <p>Our Nin services will be available shortly.</p>
                <a href="services.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Browse Other Services
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="wallet.php" class="bottom-nav-item">
        <i class="fas fa-wallet"></i>
        <span>Wallet</span>
    </a>
    <a href="services.php" class="bottom-nav-item">
        <i class="fas fa-th-large"></i>
        <span>Services</span>
    </a>
    <a href="transactions.php" class="bottom-nav-item">
        <i class="fas fa-history"></i>
        <span>History</span>
    </a>
    <a href="profile.php" class="bottom-nav-item">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
</nav>

<script>
// Animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});
</script>

</body>
</html>