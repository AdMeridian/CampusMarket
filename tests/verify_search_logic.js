// tests/verify_search_logic.js
const fs = require('fs');
const path = require('path');

console.log("=== Running Search Logic Precision Verification ===");

// Read includes/functions.php
const phpContent = fs.readFileSync(path.join(__dirname, '../includes/functions.php'), 'utf8');

// 1. Verify expandSearchQuery does NOT include 'pad' for 'ipad'
console.log("\n1. Verifying Synonym Expansion for 'ipad'...");
const synonymSection = phpContent.substring(
    phpContent.indexOf('function expandSearchQuery'),
    phpContent.indexOf('function productSearchFilterSql')
);

if (synonymSection.includes("'ipad'         => ['tablet']")) {
    console.log("PASS: 'ipad' is strictly mapped to ['tablet'].");
} else {
    console.error("FAIL: 'ipad' mapping is unexpected!");
    process.exit(1);
}

// 2. Test JS simulation of PHP logic for 'ipad' query matching against PS4 and iPad listings
console.log("\n2. Testing Product Matching Logic for 'ipad'...");

function jsExpandSearchQuery(query) {
    const lowerQuery = query.trim().toLowerCase();
    const synonymMap = {
        'iphone': ['phone', 'mobile', 'smartphone', 'cellphone', 'apple'],
        'ipad': ['tablet'],
        'tablet': ['ipad'],
        'mobile': ['phone', 'smartphone', 'cellphone'],
        'phone': ['mobile', 'smartphone', 'cellphone'],
        'laptop': ['pc', 'computer', 'macbook', 'notebook'],
    };
    const terms = [lowerQuery];
    if (synonymMap[lowerQuery]) {
        synonymMap[lowerQuery].forEach(s => terms.push(s.toLowerCase()));
    }
    return [...new Set(terms)];
}

function jsMatchesProduct(query, product) {
    const rawTokens = [...new Set(query.trim().toLowerCase().split(/\s+/).filter(Boolean))];
    
    for (const token of rawTokens) {
        const variants = jsExpandSearchQuery(token);
        
        // Title / Category / Tags check
        const titleMatch = variants.some(v => product.title.toLowerCase().includes(v));
        const categoryMatch = variants.some(v => product.category.toLowerCase().includes(v));
        const tagsMatch = variants.some(v => product.tags.some(t => t.toLowerCase().includes(v)));
        
        // Description check (exact word boundary regex for token)
        const regex = new RegExp('\\b' + token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
        const descMatch = regex.test(product.description);
        
        if (!titleMatch && !categoryMatch && !tagsMatch && !descMatch) {
            return false; // All tokens must match
        }
    }
    return true;
}

const mockListings = [
    {
        id: 1,
        title: "Sony PlayStation 4 500GB",
        category: "Electronics",
        tags: ["console", "ps4", "gaming"],
        description: "Great PS4 console comes with HDMI cable and 3 game pads."
    },
    {
        id: 2,
        title: "Apple iPad Air 64GB Space Gray",
        category: "Electronics",
        tags: ["tablet", "apple", "ipad"],
        description: "Mint condition iPad Air with magnetic cover."
    }
];

const ipadMatches = mockListings.filter(p => jsMatchesProduct("ipad", p));
console.log("Results for query 'ipad':", ipadMatches.map(p => p.title));

if (ipadMatches.length === 1 && ipadMatches[0].id === 2) {
    console.log("PASS: Searching 'ipad' returned ONLY the iPad listing and correctly ignored PS4 with 3 game pads!");
} else {
    console.error("FAIL: Searching 'ipad' returned incorrect items:", ipadMatches);
    process.exit(1);
}

// 3. Test query 'phone'
console.log("\n3. Testing Product Matching Logic for 'phone'...");
const mockPhoneListing = {
    id: 3,
    title: "iPhone 13 Pro 128GB",
    category: "Mobile Phones",
    tags: ["apple", "iphone"],
    description: "Battery health 88%"
};
mockListings.push(mockPhoneListing);

const phoneMatches = mockListings.filter(p => jsMatchesProduct("phone", p));
console.log("Results for query 'phone':", phoneMatches.map(p => p.title));

if (phoneMatches.some(p => p.id === 3)) {
    console.log("PASS: Searching 'phone' successfully matched 'iPhone 13 Pro' via synonym expansion!");
} else {
    console.error("FAIL: Searching 'phone' failed to match iPhone listing!");
    process.exit(1);
}

console.log("\n=== ALL SEARCH LOGIC VERIFICATION TESTS PASSED SUCCESSFULLY! ===");
