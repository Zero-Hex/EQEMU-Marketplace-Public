// Item Details Handler
// This file handles displaying item details in a modal and linking to the allaclone

class ItemDetailsModal {
    constructor() {
        this.modal = null;
        this.createModal();
    }

    createModal() {
        // Create modal HTML if it doesn't exist
        if (document.getElementById('itemDetailsModal')) {
            this.modal = document.getElementById('itemDetailsModal');
            return;
        }

        const modalHTML = `
            <div id="itemDetailsModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <div id="itemDetailsContent">
                        <!-- Content will be inserted here -->
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('itemDetailsModal');

        // Close button handler
        const closeBtn = this.modal.querySelector('.close');
        closeBtn.onclick = () => this.close();

        // Close when clicking outside
        window.onclick = (event) => {
            if (event.target === this.modal) {
                this.close();
            }
        };
    }

    async showItem(itemId) {
        try {
            // Fetch item details from your API
            const response = await fetch(`/api/items/get.php?id=${itemId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load item details');
            }

            const item = data.item;
            this.displayItem(item);
            this.open();

        } catch (error) {
            console.error('Error loading item details:', error);
            alert('Failed to load item details: ' + error.message);
        }
    }

    displayItem(item) {
        const content = document.getElementById('itemDetailsContent');
        
        let html = `
            <div class="item-details-header">
                <h2>${item.name}</h2>
                ${item.icon_url ? `<img src="${item.icon_url}" alt="${item.name}" class="item-icon">` : ''}
            </div>

            <div class="item-stats">
        `;

        // Combat stats
        if (item.ac && item.ac > 0) {
            html += `<p><strong>AC:</strong> ${item.ac}</p>`;
        }
        if (item.damage && item.delay) {
            html += `<p><strong>Damage/Delay:</strong> ${item.damage}/${item.delay}</p>`;
        }

        // Primary stats
        const stats = [
            { key: 'hp', label: 'HP' },
            { key: 'mana', label: 'Mana' },
            { key: 'astr', label: 'STR' },
            { key: 'asta', label: 'STA' },
            { key: 'aagi', label: 'AGI' },
            { key: 'adex', label: 'DEX' },
            { key: 'awis', label: 'WIS' },
            { key: 'aint', label: 'INT' },
            { key: 'acha', label: 'CHA' }
        ];

        stats.forEach(stat => {
            if (item[stat.key] && item[stat.key] > 0) {
                html += `<p><strong>${stat.label}:</strong> +${item[stat.key]}</p>`;
            }
        });

        // Resistances
        const resists = [
            { key: 'cr', label: 'Cold Resist' },
            { key: 'dr', label: 'Disease Resist' },
            { key: 'fr', label: 'Fire Resist' },
            { key: 'mr', label: 'Magic Resist' },
            { key: 'pr', label: 'Poison Resist' }
        ];

        resists.forEach(resist => {
            if (item[resist.key] && item[resist.key] > 0) {
                html += `<p><strong>${resist.label}:</strong> +${item[resist.key]}</p>`;
            }
        });

        // Item properties
        if (item.weight) {
            html += `<p><strong>Weight:</strong> ${item.weight}</p>`;
        }
        if (item.size) {
            html += `<p><strong>Size:</strong> ${item.size}</p>`;
        }
        if (item.itemtype) {
            html += `<p><strong>Item Type:</strong> ${item.itemtype}</p>`;
        }

        html += `
            </div>

            <div class="item-actions">
                <a href="http://65.49.60.92:8000/items/${item.id}" 
                   target="_blank" 
                   class="btn-view-allaclone">
                    🔍 View Full Details in Item Database
                </a>
            </div>
        `;

        content.innerHTML = html;
    }

    open() {
        this.modal.style.display = 'block';
    }

    close() {
        this.modal.style.display = 'none';
    }
}

// Initialize the modal when the page loads
let itemDetailsModal;
document.addEventListener('DOMContentLoaded', function() {
    itemDetailsModal = new ItemDetailsModal();
});

// Helper function to show item details (can be called from anywhere)
function showItemDetails(itemId) {
    if (!itemDetailsModal) {
        itemDetailsModal = new ItemDetailsModal();
    }
    itemDetailsModal.showItem(itemId);
}