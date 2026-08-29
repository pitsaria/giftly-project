/* Shared review-widget behaviour for the product quick-view modal.
   The reviews fragment (get_product_reviews.php) is injected as innerHTML;
   the page then calls rvBind() to wire the star picker. */

function rvBind() {
    var pick = document.getElementById('rvPick');
    if (!pick) return;
    var stars = pick.querySelectorAll('i');
    var ratingInput = document.getElementById('rvRating');

    function paint(v) {
        stars.forEach(function (s) { s.classList.toggle('on', parseInt(s.dataset.v) <= v); });
    }
    stars.forEach(function (s) {
        s.addEventListener('mouseenter', function () { paint(parseInt(s.dataset.v)); });
        s.addEventListener('click', function () {
            ratingInput.value = s.dataset.v;
            paint(parseInt(s.dataset.v));
        });
    });
    pick.addEventListener('mouseleave', function () { paint(parseInt(ratingInput.value) || 0); });
}

function rvSubmit() {
    var wrap = document.getElementById('rvWrap');
    var pid = wrap.dataset.pid;
    var rating = document.getElementById('rvRating').value;
    var comment = document.getElementById('rvComment').value;
    var msg = document.getElementById('rvMsg');
    if (!rating || rating < 1) {
        msg.style.color = '#d32f2f';
        msg.textContent = 'Pick a star rating first.';
        return;
    }
    msg.style.color = '#888';
    msg.textContent = 'Saving…';
    fetch('submit_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + encodeURIComponent(pid) + '&rating=' + encodeURIComponent(rating) +
              '&comment=' + encodeURIComponent(comment),
    })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.status === 'success') {
                if (window.reloadProductReviews) window.reloadProductReviews(pid);
            } else if (d.code === 'login_required') {
                if (window.openLoginModal) window.openLoginModal();
            } else {
                msg.style.color = '#d32f2f';
                msg.textContent = d.message || 'Could not save your review.';
            }
        })
        .catch(function () {
            msg.style.color = '#d32f2f';
            msg.textContent = 'Network error.';
        });
}

/* Fetch + inject the reviews fragment into #modalReviews for a product. */
function loadProductReviews(pid) {
    var box = document.getElementById('modalReviews');
    if (!box) return;
    box.innerHTML = '<div style="padding:16px 0;color:#aaa;font-size:13px;">Loading reviews…</div>';
    fetch('get_product_reviews.php?product_id=' + encodeURIComponent(pid))
        .then(function (r) { return r.text(); })
        .then(function (html) {
            box.innerHTML = html;
            rvBind();
        })
        .catch(function () { box.innerHTML = ''; });
}
window.reloadProductReviews = loadProductReviews;
