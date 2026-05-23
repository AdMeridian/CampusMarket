// public/js/search-suggestions.js
document.addEventListener('DOMContentLoaded', () => {
    const searchBars = document.querySelectorAll('.search-bar');
    
    searchBars.forEach(form => {
        const input = form.querySelector('.search-input');
        if (!input) return;

        // Ensure autocomplete is off
        input.setAttribute('autocomplete', 'off');

        // Check if a dropdown container already exists, if not create one
        let dropdown = form.querySelector('.search-suggestions-dropdown');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.className = 'search-suggestions-dropdown hidden';
            form.appendChild(dropdown);
        }

        let debounceTimeout = null;

        const closeDropdown = () => {
            dropdown.classList.add('hidden');
        };

        const showLoading = () => {
            dropdown.innerHTML = '<div class="suggestion-loading">Searching...</div>';
            dropdown.classList.remove('hidden');
        };

        const showEmpty = () => {
            dropdown.innerHTML = '<div class="suggestion-empty">No matching products found.</div>';
            dropdown.classList.remove('hidden');
        };

        const renderSuggestions = (results) => {
            dropdown.innerHTML = '';
            
            if (results.length === 0) {
                showEmpty();
                return;
            }

            const fragment = document.createDocumentFragment();
            
            results.forEach(product => {
                const a = document.createElement('a');
                a.href = window.__baseUrl + 'pages/product.php?id=' + product.id;
                a.className = 'suggestion-item';

                const img = document.createElement('img');
                img.src = product.image_url;
                img.alt = product.title;

                const info = document.createElement('div');
                info.className = 'suggestion-info';

                const title = document.createElement('div');
                title.className = 'suggestion-title';
                title.textContent = product.title;

                const meta = document.createElement('div');
                meta.className = 'suggestion-meta';

                const category = document.createElement('span');
                category.textContent = product.category_name;

                const price = document.createElement('span');
                price.className = 'suggestion-price';
                price.innerHTML = product.formatted_price;

                meta.appendChild(category);
                meta.appendChild(price);

                info.appendChild(title);
                info.appendChild(meta);

                a.appendChild(img);
                a.appendChild(info);

                fragment.appendChild(a);
            });

            dropdown.appendChild(fragment);
            dropdown.classList.remove('hidden');
        };

        const fetchSuggestions = (query) => {
            if (query.length < 2) {
                closeDropdown();
                return;
            }

            showLoading();

            fetch(window.__baseUrl + 'pages/api_search_suggestions.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Check if input value changed while fetching
                        if (input.value.trim() === query) {
                            renderSuggestions(data.results);
                        }
                    } else {
                        closeDropdown();
                    }
                })
                .catch(err => {
                    console.error('Search suggestion error:', err);
                    closeDropdown();
                });
        };

        input.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            clearTimeout(debounceTimeout);
            
            if (query.length < 2) {
                closeDropdown();
                return;
            }

            debounceTimeout = setTimeout(() => {
                fetchSuggestions(query);
            }, 300);
        });

        input.addEventListener('focus', () => {
            const query = input.value.trim();
            if (query.length >= 2 && dropdown.innerHTML !== '') {
                dropdown.classList.remove('hidden');
            }
        });

        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!form.contains(e.target)) {
                closeDropdown();
            }
        });

        // Close on Escape key
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDropdown();
                input.blur();
            }
        });
    });
});
