
add_action('wp_ajax_get_bubble_images', 'get_bubble_images');
add_action('wp_ajax_nopriv_get_bubble_images', 'get_bubble_images');

function get_bubble_images() {
    $post_id = $_POST['post_id'];
    $pictures = [];

    if (have_rows('bubble_image', $post_id)) {
        while (have_rows('bubble_image', $post_id)) {
            the_row();

            if (get_sub_field('image_bubble')) {
                $image = get_sub_field('image_bubble');
                $image_url = $image['url'];
                $pictures[] = $image_url;
            }
        }
    }

    // image -----> convert to -----> json response
    wp_send_json($pictures);
    wp_die();
}
function bubble_animation_shortcode() {
    global $post;
    ob_start(); ?>

    <div id="bubble-container"></div>
    <script src="https://cdn.jsdelivr.net/npm/quad-tree.as@0.1.3/dist/src/index.min.js"></script>

    <script>
        jQuery(document).ready(function() {
            console.log("inside");
            var bubbleCount = 6; // Total number of bubbles
            var bubbleSizeRange = [80, 150]; // Range of bubble sizes
            var boxWidth = 450; // Width of the containing box
            var boxHeight = 500; // Height of the containing box
            var pictures = [];
            var animationPaused = false; // Flag to track animation state

            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_bubble_images',
                    post_id: <?php echo $post->ID; ?>
                },
                success: function(response) {
                    pictures = response;
                    console.log(pictures);
                },
                error: function(error) {
                    console.error('Error fetching images: ' + error);
                }
            });

            function getRandomSize(min, max) {
                return Math.floor(Math.random() * (max - min + 1)) + min;
            }

            function getRandomImage() {
                return pictures[Math.floor(Math.random() * pictures.length)];
            }

            function getRandomColor() {
                var colors = ['#003366', '#FF9900', '#87CEEB', '#B0C4DE', '#FFA500', '#B0E0E6', '#66B2FF'];
                return colors[Math.floor(Math.random() * colors.length)];
            }

            var cellSize = Math.max.apply(null, bubbleSizeRange) * 1.5; // Size of each grid cell
            var grid = []; // Grid to store bubbles

            function createGrid() {
                var cols = Math.ceil(boxWidth / cellSize);
                var rows = Math.ceil(boxHeight / cellSize);

                for (var i = 0; i < cols; i++) {
                    grid[i] = [];
                    for (var j = 0; j < rows; j++) {
                        grid[i][j] = null;
                    }
                }
            }

            function placeBubbleInGrid(bubble) {
                var col = Math.floor(bubble.x / cellSize);
                var row = Math.floor(bubble.y / cellSize);

                if (!grid[col]) grid[col] = [];
                grid[col][row] = bubble;
            }

            function checkCollisionWithGrid(bubble) {
                var col = Math.floor(bubble.x / cellSize);
                var row = Math.floor(bubble.y / cellSize);

                if (!grid[col]) return false;

                for (var i = Math.max(0, col - 1); i <= Math.min(grid.length - 1, col + 1); i++) {
                    for (var j = Math.max(0, row - 1); j <= Math.min(grid[i].length - 1, row + 1); j++) {
                        if (grid[i][j] && grid[i][j] !== bubble) {
                            var dx = bubble.x - grid[i][j].x;
                            var dy = bubble.y - grid[i][j].y;
                            var distance = Math.sqrt(dx * dx + dy * dy);

                            if (distance < (bubble.size + grid[i][j].size) / 2) {
                                return true; // Collision detected
                            }
                        }
                    }
                }

                return false; // No collision detected
            }

            function generateNonOverlappingPosition(size) {
                var maxAttempts = 100;

                for (var attempt = 0; attempt < maxAttempts; attempt++) {
                    var x = Math.random() * (boxWidth - size);
                    var y = Math.random() * (boxHeight - size);

                    var collides = false;

                    // Check for collisions with bubbles in the same and adjacent grid cells
                    var tempBubble = { x: x, y: y, size: size };
                    collides = checkCollisionWithGrid(tempBubble);

                    if (!collides) {
                        // No collision detected, place the bubble in the grid and return its position
                        placeBubbleInGrid(tempBubble);
                        return { x: x, y: y, size: size };
                    }
                }

                // If maximum attempts reached without finding a non-overlapping position, return null
                return null;
            }

            function animateBubble(bubble, colorBubble) {
                if (animationPaused) return; // Pause animation when not visible

                var size = getRandomSize(bubbleSizeRange[0], bubbleSizeRange[1]);
                var position = generateNonOverlappingPosition(size);

                if (position) {
                    var randomX = position.x;
                    var randomY = position.y;

                    var randomDirectionX = Math.random() > 0.5 ? 1 : -1;
                    var randomDirectionY = Math.random() > 0.5 ? 1 : -1;

                    var imageUrl = getRandomImage();
                    var bubbleColor = getRandomColor();

                    bubble.css({
                        width: size + 'px',
                        height: size + 'px',
                        left: randomX + 'px',
                        top: randomY + 'px',
                        background: 'url(' + imageUrl + ')',
                        backgroundSize: 'cover',
                        display: 'none'
                    });

                    colorBubble.css({
                        width: 50 + 'px',
                        height: 50 + 'px',
                        left: randomX + 'px',
                        top: randomY + 'px',
                        background: bubbleColor,
                        display: 'none'
                    });

                    var animationPropertiesX = {
                        left: (randomX + randomDirectionX * Math.random() * boxWidth) + 'px',
                        opacity: 0,
                    };

                    var animationPropertiesY = {
                        top: (randomY + randomDirectionY * Math.random() * boxHeight) + 'px',
                        opacity: 0,
                    };

                    colorBubble.fadeIn(1000).animate(animationPropertiesY, 3000, function() {
                        colorBubble.remove();
                    });

                    setTimeout(function() {
                        bubble.fadeIn(500).animate(animationPropertiesX, 5000, function() {
                            bubble.remove();
                        });
                    }, 1000);

                    setTimeout(function() {
                        createAndAnimateBubble();
                    }, 1500);
                } else {
                    // Handle the case where a non-overlapping position couldn't be found.
                    bubble.remove();
                    createAndAnimateBubble(); // Try again with a new bubble
                }
            }

            var colorBubble = jQuery('<div class="bubble"></div>');

            function createAndAnimateBubble() {
                var bubble = jQuery('<div class="bubble"></div>');
                var colorBubbleClone = colorBubble.clone();
                jQuery('#bubble-container').append(bubble, colorBubbleClone);
                animateBubble(bubble, colorBubbleClone);
            }

            // Initialize the grid
            createGrid();

            // Pause animation when tab is not visible
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    animationPaused = true;
                } else {
                    animationPaused = false;
                    // Resume animation
                    createAndAnimateBubble();
                }
            });

            createAndAnimateBubble();
        });
    </script>

    <style>
        .bubble {
            position: absolute;
        }
    </style>

        <?php
        return ob_get_clean();
}
add_shortcode('bubble_animation', 'bubble_animation_shortcode');
