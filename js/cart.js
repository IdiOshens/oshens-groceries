/**
 * Cart Operations JavaScript
 * Handles add to cart, update cart, and cart display
 */

// Add to cart function
function addToCart(productId, quantity = 1) {
    // Check if user is logged in (check via API)
    fetch('cart_operations.php?action=get_cart')
        .then(response => response.json())
        .then(data => {
            if (!data.success && data.message && data.message.includes('login')) {
                if (confirm('Please login to add items to cart. Would you like to login now?')) {
                    window.location.href = 'login.php';
                }
                return;
            }
            // Continue with add to cart
            performAddToCart(productId, quantity);
        })
        .catch(() => {
            // If error, try to add anyway
            performAddToCart(productId, quantity);
        });
}

// Perform the actual add to cart operation
function performAddToCart(productId, quantity = 1) {
    
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    
    fetch('cart_operations.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showNotification('Item added to cart!', 'success');
            // Update cart count
            updateCartDisplay();
        } else {
            if (data.message && data.message.includes('login')) {
                if (confirm('Please login to add items to cart. Would you like to login now?')) {
                    window.location.href = 'login.php';
                }
            } else {
                showNotification(data.message || 'Failed to add item to cart', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Update cart display
function updateCartDisplay() {
    fetch('cart_operations.php?action=get_cart', {
        method: 'GET'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count and total
            const cartCountEl = document.querySelector('.cart-count');
            const cartTotalEl = document.querySelector('.cart-total');
            
            if (cartCountEl) {
                cartCountEl.textContent = data.count;
            }
            if (cartTotalEl) {
                cartTotalEl.textContent = 'UGX ' + formatNumber(data.total);
            }
            
            // Update cart sidebar if open
            updateCartSidebar(data.items, data.total);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Update cart sidebar
function updateCartSidebar(items, total) {
    const cartItemsContainer = document.getElementById('cart-items-container');
    if (!cartItemsContainer) return;
    
    if (items.length === 0) {
        cartItemsContainer.innerHTML = '<p class="text-center text-muted">Your cart is empty</p>';
        return;
    }
    
    let html = '<ul class="list-group mb-3">';
    items.forEach(item => {
        html += `
            <li class="list-group-item d-flex justify-content-between lh-sm">
                <div>
                    <h6 class="my-0">${escapeHtml(item.product_name)}</h6>
                    <small class="text-body-secondary">Qty: ${item.quantity} × UGX ${formatNumber(item.price)}</small>
                </div>
                <span class="text-body-secondary">UGX ${formatNumber(item.subtotal)}</span>
            </li>
        `;
    });
    html += '</ul>';
    html += `
        <div class="d-flex justify-content-between mb-3">
            <strong>Total:</strong>
            <strong>UGX ${formatNumber(total)}</strong>
        </div>
        <a href="checkout.php" class="btn btn-primary w-100">Proceed to Checkout</a>
    `;
    
    cartItemsContainer.innerHTML = html;
}

// Update cart item quantity
function updateCartItem(cartId, quantity) {
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('cart_id', cartId);
    formData.append('quantity', quantity);
    
    fetch('cart_operations.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartDisplay();
        } else {
            showNotification(data.message || 'Failed to update cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Remove cart item
function removeCartItem(cartId) {
    if (!confirm('Are you sure you want to remove this item from cart?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('cart_id', cartId);
    
    fetch('cart_operations.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartDisplay();
            showNotification('Item removed from cart', 'success');
        } else {
            showNotification(data.message || 'Failed to remove item', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Load cart on page load
document.addEventListener('DOMContentLoaded', function() {
    // Always try to update cart display (will return empty if not logged in)
    updateCartDisplay();
    
    // Handle add to cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            const quantityInput = this.closest('.product-item')?.querySelector('.input-number, .product-quantity');
            const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
            addToCart(productId, quantity);
        });
    });
    
    // Handle quantity buttons
    document.querySelectorAll('.quantity-left-minus, .quantity-right-plus').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.product-qty')?.querySelector('.input-number, .product-quantity');
            if (input) {
                let value = parseInt(input.value) || 1;
                if (this.classList.contains('quantity-left-minus')) {
                    value = Math.max(1, value - 1);
                } else {
                    const max = parseInt(input.getAttribute('max')) || 999;
                    value = Math.min(max, value + 1);
                }
                input.value = value;
            }
        });
    });
});

