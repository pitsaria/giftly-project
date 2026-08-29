<?php 
include 'db_connect.php'; 
include 'header.php'; 
?>

<style>
           /* CAROUSEL STYLES */
    .carousel-wrapper {
        width: 100%; 
        max-width: 1300px; /* Made slightly wider to accommodate the bigger image */
        margin: 0 auto 60px auto; 
        padding: 0 20px;
    }

    .carousel-container {
        position: relative; 
        width: 100%; 
        height: 650px; /* INCREASED from 560px to 650px */
        border-radius: 35px;
        overflow: hidden; 
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
        display: flex; 
        align-items: center;
    }
    
    /* Update slide text to be bigger to match */
    .slide-text h1 { font-size: 52px; line-height: 1.2; font-weight: 600; color: #222; margin-bottom: 15px; }
    .slide-text h1 span { color: #ff8ba7; }
    .slide-text p { font-size: 18px; color: #555; margin-bottom: 30px; max-width: 450px; line-height: 1.5; }
    .slide { 
        position: absolute; 
        top: 0; left: 0;
        width: 100%; 
        height: 100%; 
        display: flex; 
        align-items: center; 
        opacity: 0; 
        transition: opacity 1s ease; 
        z-index: 0; 
        padding: 0 60px; 
    }
    .slide.active { opacity: 1; z-index: 1; }
    
    /* --- YOUR EXACT GRADIENT COLORS RESTORED HERE --- */
        /* Slide 1 - Pink, Cream, White, Purple (Your existing one) */
    .slide-1 { background: linear-gradient(225deg, #FFDBDF 0%, #fff4d8 20%, #ffffff 60%, #E2D5F1 150%); }

    /* Slide 2 - Fresh Mint, Soft Blue, White, Lavender (For Collections) */
    .slide-2 { background: linear-gradient(225deg, #D6EAF8 0%, #fff1da 20%, #ffffff 60%, #F4ECF7 150%); }

    /* Slide 3 - Golden Yellow, Peach, White, Soft Pink (For Bundles) */
    .slide-3 { background: linear-gradient(225deg, #FDEBD0 0%, #FCF3CF 20%, #ffffff 60%, #FDEDEC 150%); }
    /* ------------------------------------------------- */
    
    .slide-text { flex: 1; padding-right: 20px; }
    .slide-text h1 { font-size: 44px; line-height: 1.2; font-weight: 600; color: #222; margin-bottom: 15px; }
    .slide-text h1 span { color: #ff8ba7; }
    .slide-text p { font-size: 16px; color: #555; margin-bottom: 30px; max-width: 400px; line-height: 1.5; }
    .hero-buttons { display: flex; gap: 15px; }

    .carousel-controls { 
        position: absolute; 
        bottom: 25px; 
        left: 50%; 
        transform: translateX(-50%); 
        z-index: 10; 
        display: flex; 
        align-items: center; 
        gap: 20px; 
        background: rgba(255,255,255,0.85); 
        backdrop-filter: blur(8px); 
        padding: 12px 30px; 
        border-radius: 50px; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
    }
    .arrow { font-size: 18px; color: #444; cursor: pointer; transition: 0.2s; }
    .arrow:hover { color: #ff8ba7; transform: scale(1.1); }
    .dots { display: flex; gap: 10px; }
    .dot { width: 10px; height: 10px; border-radius: 50%; background: #ccc; cursor: pointer; transition: 0.3s; }
    .dot.active { background: #ff8ba7; width: 30px; border-radius: 10px; }

            /* --- SECTION STYLES --- */
    .section-header { text-align: center; font-size: 28px; font-weight: 600; margin-bottom: 25px; color: #111; }
    
    /* The Big White Container */
    .featured-container {
        background: #fdfdfd;
        border-radius: 50px;
        padding: 40px 30px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.03);
        margin-bottom: 40px;
    }

    /* --- PRODUCT CARD STYLES --- */
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 20px; }    
    .product-card { 
        background: #fff; 
        border-radius: 30px 30px 16px 16px; /* Rounded top, slightly rounded bottom */
        padding: 20px 20px 15px; 
        text-align: center; 
        position: relative; 
        box-shadow: 0 5px 15px rgba(0,0,0,0.04); 
        transition: transform 0.3s, box-shadow 0.3s;
        display: flex; flex-direction: column;
    }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
    
    .p-image-container { background: #fff; border-radius: 20px; padding: 10px; margin-bottom: 10px; text-align: center; }
    .p-image { max-width: 100%; height: 160px; object-fit: contain; }
    
       .p-name { font-size: 15px; font-weight: 500; color: #222; margin-bottom: 3px; line-height: 1.3; }
       .p-desc { 
        font-size: 13px; 
        color: #555; 
        line-height: 1.5; 
        margin-bottom: 12px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;  /* THIS FORCES IT TO EXACTLY 2 LINES */
        -webkit-box-orient: vertical;
        min-height: 40px;       /* Ensures the box stays the same height even if empty */
    }
    .p-price { font-size: 15px; font-weight: 700; color: #111; }

    .p-bottom-row { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 5px; }
    
        /* --- THE GRADIENT FOOTERS --- */
    .card-pink { background: linear-gradient(135deg, #ffecec 0%, #ffd6d9 100%); border-radius: 0 0 16px 16px; padding: 15px 20px; margin: 0 -20px -15px; flex-grow: 1;}
    .card-purple { background: linear-gradient(135deg, #f3edff 0%, #e1d5f4 100%); border-radius: 0 0 16px 16px; padding: 15px 20px; margin: 0 -20px -15px; flex-grow: 1;}
    .card-yellow { background: linear-gradient(135deg, #fff9e6 0%, #fff4d4 100%); border-radius: 0 0 16px 16px; padding: 15px 20px; margin: 0 -20px -15px; flex-grow: 1;}
    .card-blue { background: linear-gradient(135deg, #eaf3ff 0%, #dbe8f7 100%); border-radius: 0 0 16px 16px; padding: 15px 20px; margin: 0 -20px -15px; flex-grow: 1;}
    
        .cart-icon { font-size: 18px; color: #ff8ba7; cursor: pointer; transition: 0.2s; }
    .cart-icon:hover { color: #e6738f; transform: scale(1.1); }

        /* --- HOW IT WORKS (BIGGER & BETTER) --- */
    .works-container { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 50px 40px; 
        background: #fff; 
        border-radius: 50px; 
        margin: 20px 0 50px; 
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.03);
    }
    
    .work-step { text-align: center; width: 20%; }
    
    /* THE CIRCLE ART CONTAINER - MASSIVELY BIGGER */
    .work-icon { 
        width: 160px; 
        height: 160px; 
        border-radius: 50%; 
        margin: 0 auto 15px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 50px; 
        color: #ff8ba7;
        box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        transition: transform 0.3s ease;
    }
    .work-icon:hover { transform: scale(1.05); }
    
    /* If you put images inside, this will make them fill the circle perfectly */
    .work-icon img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
        border-radius: 50%;
    }
    
    /* BACKGROUNDS FOR THE CIRCLES */
    .w-browse { background: #fff0f5; } 
    .w-order { background: #ffe4e1; } 
    .w-prepare { background: #f3e5f5; } 
    .w-deliver { background: #e3f2fd; }
    
    .work-step h3 { 
        font-size: 20px; 
        font-weight: 600; 
        margin-top: 10px; 
        color: #222;
    }
    
    .work-arrow { 
        font-size: 30px; 
        color: #ddd; 
        width: 5%; 
        text-align: center; 
    }
       /* --- PROMOTIONS (BIGGER & IMAGE-READY) --- */
    .promo-grid { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 25px; 
        padding: 30px 0 50px; 
    }
    
    .promo-card { 
        padding: 35px 30px; 
        border-radius: 30px; 
        transition: transform 0.3s, box-shadow 0.3s;
        display: flex; 
        justify-content: space-between; 
        align-items: center;
        min-height: 180px;
        cursor: default;
    }
    .promo-card:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 10px 30px rgba(0,0,0,0.04);
    }
    
    /* Left side: Text */
    .promo-text { flex: 1; padding-right: 20px; }
    .promo-title { 
        font-size: 24px; 
        font-weight: 700; 
        margin-bottom: 10px; 
        color: #222; 
        line-height: 1.2;
    }
    .promo-desc { 
        font-size: 15px; 
        color: #555; 
        line-height: 1.5; 
        max-width: 300px;
    }

    /* Right side: Image container */
    .promo-image { 
        flex: 0 0 100px; 
        height: 100px; 
        display: flex; 
        justify-content: center; 
        align-items: center;
    }
    .promo-image img { 
        max-width: 100%; 
        max-height: 100%; 
        object-fit: contain; 
    }

    /* --- THE PASTEL GRADIENT BACKGROUNDS --- */
    .p-birthday { background: linear-gradient(135deg, #fff0f5, #ffd6d9); }
    .p-bundle { background: linear-gradient(135deg, #f3edff, #e1d5f4); }
    .p-shipping { background: linear-gradient(135deg, #eaf3ff, #dbe8f7); }
    .p-seasonal { background: linear-gradient(135deg, #fff9e6, #fff4d4); }

    /* --- REVIEWS --- */
    .review-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; background: #f8f8fa; padding: 40px; border-radius: 35px; }
    .review-card { background: #fff; padding: 30px; border-radius: 24px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
    .r-avatar { width: 60px; height: 60px; border-radius: 50%; background: #ffc1cc; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; }
    .r-avatar i { font-size: 30px; color: #fff; }
    .r-stars { color: #ffc107; font-size: 14px; margin-bottom: 10px; }
    .r-text { font-size: 12px; color: #555; line-height: 1.6; }
    
    .btn-review-container { text-align: center; padding: 40px 0 0; }
    .btn-review { background: #ffc1cc; color: white; border: none; padding: 12px 40px; border-radius: 50px; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-review:hover { background: #ff8ba7; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(255, 139, 167, 0.3); }

            /* --- BUTTONS --- */
        .btn-primary { 
            background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
            color: white; 
            padding: 12px 25px; 
            border-radius: 50px; 
            font-weight: 600; 
            font-size: 14px; 
            border: none; 
            cursor: pointer; 
            transition: 0.2s;
            box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, #ff8ba7 0%, #FEA5B6 100%); 
            transform: translateY(-2px); 
            box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
        }
        
               .btn-secondary { 
            background: transparent; 
            color: #222; 
            border: 1px solid #ddd; 
            padding: 12px 25px; 
            border-radius: 50px; 
            font-weight: 600; 
            font-size: 14px; 
            cursor: pointer; 
            transition: all 0.2s ease; 
        }
        .btn-secondary:hover { 
            border-color: #FEA5B6; 
            color: #FEA5B6; 
            transform: translateY(-2px); /* 🚀 Makes it float! */
            box-shadow: 0 6px 16px rgba(254, 165, 182, 0.2);
        }

</style>

    <!-- 1. HERO CAROUSEL -->
<div class="carousel-wrapper">
    <div class="carousel-container">
        
        <!-- SLIDE 1: Build Your Box -->
        <div class="slide slide-1 active">
            <div class="slide-text" style="flex: 0.9;">
                <h1>Make Every <span>Surprise</span><br>More Meaningful</h1>
                <p>Create personalized gift boxes or choose curated collections for every occasion.</p>
                <div class="hero-buttons">
                    <a href="build-a-box.php" class="btn-primary" style="padding: 14px 35px; text-decoration:none; display:inline-block;">Build your Box</a>
                    <a href="shop.php" class="btn-secondary" style="padding: 14px 35px; text-decoration:none; display:inline-block;">Shop Collection</a>
                </div>
            </div>
            
            <div class="slide-graphic" style="flex: 1.2; height: 100%; display: flex; justify-content: flex-end; align-items: center; padding-right: 15px;">
                <img src="giftbox.png" alt="Gift Box" style="max-width: 115%; max-height: 110%; object-fit: contain; transform: translateX(80px);">
            </div>
        </div>

        <!-- SLIDE 2: Occasion Box - MOVED RIGHT & 5% SMALLER -->
        <div class="slide slide-2">
            <div class="slide-text" style="flex: 0.85;">
                <h1>Perfect <span>Occasion</span><br>Gift Boxes</h1>
                <p>Curated gifts for birthdays, anniversaries, weddings, and every special moment.</p>
                <div class="hero-buttons">
                    <a href="occasion-boxes.php" class="btn-primary" style="padding: 14px 35px; text-decoration:none; display:inline-block;">Shop Occasion Boxes</a>
                    <a href="shop.php" class="btn-secondary" style="padding: 14px 35px; text-decoration:none; display:inline-block;">Explore Gifts</a>
                </div>
            </div>
            
            <div class="slide-graphic" style="flex: 1.25; height: 100%; display: flex; justify-content: flex-end; align-items: center; padding-right: 0px;">
                <img src="occasion_box.png" alt="Occasion Box" style="max-width: 120%; max-height: 115%; object-fit: contain; transform: translateX(100px);">
            </div>
        </div>

        <!-- SLIDE 3: Giftly Basket -->
        <div class="slide slide-3">
            <div class="slide-text" style="flex: 0.85;">
                <h1>Giftly <span>Basket</span><br>Delights</h1>
                <p>Beautifully arranged baskets filled with premium goodies for any celebration.</p>
                <div class="hero-buttons">
                    <a href="baskets.php" class="btn-primary" style="padding: 14px 35px; text-decoration:none; display:inline-block;">View Baskets</a>
                    <a href="shop.php" class="btn-secondary" style="padding: 14px 35px; text-decoration:none; display:inline-block;">Start Shopping</a>
                </div>
            </div>
            
            <div class="slide-graphic" style="flex: 1.25; height: 100%; display: flex; justify-content: flex-end; align-items: center; padding-right: 15px;">
                <img src="giftly_basket.png" alt="Giftly Basket" style="max-width: 125%; max-height: 120%; object-fit: contain; transform: translateX(80px);">
            </div>
        </div>

        <!-- CONTROLS -->
        <div class="carousel-controls">
            <div class="arrow" id="prevBtn"><i class="fas fa-chevron-left"></i></div>
            <div class="dots" id="dotsContainer">
                <div class="dot active" data-index="0"></div>
                <div class="dot" data-index="1"></div>
                <div class="dot" data-index="2"></div>
            </div>
            <div class="arrow" id="nextBtn"><i class="fas fa-chevron-right"></i></div>
        </div>
        
    </div>
</div>

<!-- 2. FEATURED PRODUCTS -->
<div class="container">
    <div class="featured-container">
        <h2 class="section-header">Featured Products</h2>
        
        <div class="product-grid">
            <?php
            // Showing actual products from your database!
            $sql = "SELECT * FROM products LIMIT 4"; 
            $result = $conn->query($sql);
            
            // Array of colors to cycle through for the bottom of the cards
            $card_colors = ['card-pink', 'card-purple', 'card-yellow', 'card-blue'];
            $color_index = 0;

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    // Assign the color based on the loop
                    $current_color = $card_colors[$color_index % count($card_colors)];
                    $color_index++;
                    ?>
                    
                    <div class="product-card">
                        <div class="p-image-container">
                            <img src="uploads/<?php echo $row['image']; ?>" alt="Product" class="p-image">
                        </div>
                        
                        <div class="<?php echo $current_color; ?>">
                            <div class="p-name"><?php echo $row['name']; ?></div>
                            
                            <!-- NEW DESCRIPTION PREVIEW -->
                            <div class="p-desc"><?php echo substr(htmlspecialchars($row['description']), 0, 40) . '...'; ?></div>                        
                            <div class="p-bottom-row">
                                <div class="p-price">PHP <?php echo number_format($row['price'], 2); ?></div>
                                
                                <!-- 🚨 UPDATED: Check if user is logged in -->
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <!-- User is logged in - use form -->
                                    <form action="add_to_cart.php" method="POST" style="margin:0; display:inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" style="background:none; border:none; cursor:pointer;">
                                            <i class="fas fa-shopping-cart cart-icon"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- User is NOT logged in - show login modal -->
                                    <button onclick="openLoginModal()" style="background:none; border:none; cursor:pointer;">
                                        <i class="fas fa-shopping-cart cart-icon"></i>
                                    </button>
                                <?php endif; ?>
                                
                            </div>
                        </div>
                    </div>

                    <?php
                }
            } else {
                echo "<p style='grid-column: span 4; text-align:center; padding:40px;'>Add some products in your Admin panel first!</p>";
            }
            ?>
        </div>
    </div>
</div>

<!-- 3. HOW IT WORKS -->
<div class="container">
    <h2 class="section-header" style="font-size: 32px; margin-bottom: 30px;">How It Works</h2>
    <div class="works-container">
        
        <!-- Step 1: Browse -->
        <div class="work-step">
            <div class="work-icon w-browse">
                <!-- Put your step1.png here -->
                <img src="step3.png" alt="Browse">
            </div>
            <h3>Browse</h3>
        </div>
        
        <div class="work-arrow"><i class="fas fa-arrow-right"></i></div>
        
        <!-- Step 2: Order -->
        <div class="work-step">
            <div class="work-icon w-order">
                <!-- Put your step2.png here -->
                <img src="step1.png" alt="Order">
            </div>
            <h3>Order</h3>
        </div>
        
        <div class="work-arrow"><i class="fas fa-arrow-right"></i></div>
        
        <!-- Step 3: Prepare -->
        <div class="work-step">
            <div class="work-icon w-prepare">
                <!-- Put your step3.png here -->
                <img src="step2.png" alt="Prepare">
            </div>
            <h3>Prepare</h3>
        </div>
        
        <div class="work-arrow"><i class="fas fa-arrow-right"></i></div>
        
        <!-- Step 4: Deliver -->
        <div class="work-step">
            <div class="work-icon w-deliver">
                <!-- Put your step4.png here -->
                <img src="step3.png" alt="Deliver">
            </div>
            <h3>Deliver</h3>
        </div>
        
    </div>
</div>

<!-- 4. SPECIAL PROMOTIONS -->
<div class="container">
    <h2 class="section-header" style="font-size: 32px;">Special Promotions</h2>
    <div class="promo-grid">
        
        <!-- 1. Birthday Special -->
        <div class="promo-card p-birthday">
            <div class="promo-text">
                <div class="promo-title">Birthday<br>Special</div>
                <div class="promo-desc">Get 15% OFF on selected Birthday Boxes and celebration gifts.</div>
            </div>
            <div class="promo-image">
                <!-- <img src="birthday-icon.png" alt="Birthday"> -->
                <i class="fas fa-birthday-cake" style="font-size: 60px; color: rgba(0,0,0,0.1);"></i>
            </div>
        </div>

        <!-- 2. Bundle & Save -->
        <div class="promo-card p-bundle">
            <div class="promo-text">
                <div class="promo-title">Bundle &<br>Save</div>
                <div class="promo-desc">Buy any Giftly Bundle and save up to 20%</div>
            </div>
            <div class="promo-image">
                <!-- <img src="bundle-icon.png" alt="Bundle"> -->
                <i class="fas fa-gifts" style="font-size: 60px; color: rgba(0,0,0,0.1);"></i>
            </div>
        </div>

        <!-- 3. Free Shipping -->
        <div class="promo-card p-shipping">
            <div class="promo-text">
                <div class="promo-title">Free<br>Shipping</div>
                <div class="promo-desc">Enjoy FREE delivery on orders over P1,500.</div>
            </div>
            <div class="promo-image">
                <!-- <img src="shipping-icon.png" alt="Shipping"> -->
                <i class="fas fa-truck" style="font-size: 60px; color: rgba(0,0,0,0.1);"></i>
            </div>
        </div>

        <!-- 4. Seasonal Collection -->
        <div class="promo-card p-seasonal">
            <div class="promo-text">
                <div class="promo-title">Seasonal<br>Collection</div>
                <div class="promo-desc">Shop exclusive limited-edition gift boxes for holidays</div>
            </div>
            <div class="promo-image">
                <!-- <img src="seasonal-icon.png" alt="Seasonal"> -->
                <i class="fas fa-leaf" style="font-size: 60px; color: rgba(0,0,0,0.1);"></i>
            </div>
        </div>

    </div>
</div>

<!-- 5. CUSTOMER REVIEWS -->
<div class="container">
    <h2 class="section-header">Customer Reviews</h2>
    <div class="review-grid">
        <div class="review-card">
            <div class="r-avatar"><i class="fas fa-user"></i></div>
            <div class="r-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
            <div class="r-text">"The gift box was beautifully packaged and arrived on time. My friend absolutely loved the surprise!"</div>
        </div>
        <div class="review-card">
            <div class="r-avatar"><i class="fas fa-user"></i></div>
            <div class="r-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
            <div class="r-text">"The gift box was beautifully packaged and arrived on time. My friend absolutely loved the surprise!"</div>
        </div>
        <div class="review-card">
            <div class="r-avatar"><i class="fas fa-user"></i></div>
            <div class="r-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
            <div class="r-text">"The gift box was beautifully packaged and arrived on time. My friend absolutely loved the surprise!"</div>
        </div>
    </div>
    <div class="btn-review-container">
        <button class="btn-review">+ Leave a Review</button>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    let currentIndex = 0, autoSlideInterval;

    function updateCarousel(index) {
        slides.forEach(s => s.classList.remove('active'));
        dots.forEach(d => d.classList.remove('active'));
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        currentIndex = index;
    }

    nextBtn.addEventListener('click', () => { updateCarousel((currentIndex + 1) % slides.length); resetTimer(); });
    prevBtn.addEventListener('click', () => { updateCarousel((currentIndex - 1 + slides.length) % slides.length); resetTimer(); });
    dots.forEach(d => d.addEventListener('click', (e) => { updateCarousel(parseInt(e.target.dataset.index)); resetTimer(); }));

    function startTimer() { autoSlideInterval = setInterval(() => updateCarousel((currentIndex + 1) % slides.length), 5000); }
    function resetTimer() { clearInterval(autoSlideInterval); startTimer(); }
    startTimer();
</script>

<script>
    // 🚨 Show alert and open login modal
    function showLoginAlert() {
        // Optional: Show a sweet alert or notification
        alert('Please log in to add items to your cart! 🎁');
        // Open the login modal
        openLoginModal();
    }
</script>