// Carousel functionality
document.addEventListener('DOMContentLoaded', function() {
    // Guest Mode toggle
    const guestModeBtn = document.getElementById('guestModeBtn');
    const guestPostForm = document.getElementById('guestPostForm');
    
    if (guestModeBtn) {
        guestModeBtn.addEventListener('click', function() {
            // Sembunyikan alert info
            this.closest('.alert').style.display = 'none';
            // Tampilkan form guest post
            guestPostForm.style.display = 'block';
        });
    }
    
    // Carousel code
    const carouselContainer = document.querySelector('.carousel-container');
    if (!carouselContainer) return;
    
    let currentIndex = 0;
    let allPosts = [];
    
    // Fetch trending posts data from a global variable (assuming it's set in the PHP file)
    if (typeof trendingPosts !== 'undefined') {
        allPosts = trendingPosts;
    }

    function createPostElement(post) {
        let fileContent = '';
        if (post.file_url) {
            if (post.file_type && post.file_type.startsWith('image/')) {
                fileContent = `<img src="${post.file_url}" class="post-image" alt="Post image">`;
            } else if (post.file_type && post.file_type.startsWith('video/')) {
                fileContent = `
                    <div class="post-file">
                        <video controls>
                            <source src="${post.file_url}" type="${post.file_type}">
                            Your browser does not support video playback.
                        </video>
                    </div>`;
            } else if (post.file_type && post.file_type.startsWith('audio/')) {
                fileContent = `
                    <div class="post-file">
                        <audio controls>
                            <source src="${post.file_url}" type="${post.file_type}">
                            Your browser does not support audio playback.
                        </audio>
                    </div>`;
            }
        }

        // Check if this post is marked as not_interested
        const isNotInterested = post.is_not_interested == 1;
        
        // Add category badges if any
        let categoriesHtml = '';
        if (post.categories) {
            const categoryNames = post.categories.split(',');
            const categoryIds = post.category_ids ? post.category_ids.split(',') : [];
            
            categoriesHtml = `<div class="px-4 py-2">`;
            for (let i = 0; i < categoryNames.length; i++) {
                const category = categoryNames[i];
                const categoryId = categoryIds[i] || '';

                // Determine category style
                let categoryClass = 'category-badge';
                let icon = 'tag';
                const catLower = category.toLowerCase();

                // Apply styling based on category
                if (catLower.includes('politic')) {
                    categoryClass += ' category-politics';
                    icon = 'landmark';
                } else if (catLower.includes('tech')) {
                    categoryClass += ' category-technology';
                    icon = 'microchip';
                } else if (catLower.includes('entertain')) {
                    categoryClass += ' category-entertainment';
                    icon = 'film';
                } else if (catLower.includes('sport')) {
                    categoryClass += ' category-sports';
                    icon = 'futbol';
                } else if (catLower.includes('health')) {
                    categoryClass += ' category-health';
                    icon = 'heartbeat';
                } else if (catLower.includes('science')) {
                    categoryClass += ' category-science';
                    icon = 'flask';
                } else if (catLower.includes('business')) {
                    categoryClass += ' category-business';
                    icon = 'briefcase';
                } else if (catLower.includes('education')) {
                    categoryClass += ' category-education';
                    icon = 'graduation-cap';
                }

                categoriesHtml += `
                    <a href="index.php?categories[]=${categoryId}" class="${categoryClass} text-decoration-none">
                        <i class="fas fa-${icon} fa-xs"></i> ${category}
                    </a>`;
            }
            categoriesHtml += `</div>`;
        }
        
        const interactionHtml = isNotInterested ? 
            `<div class="post-stats">
                <a href="show_again.php?id=${post.id}" class="btn btn-success btn-sm w-100">
                    <i class="fas fa-eye"></i> Show Again
                </a>
            </div>` :
            `<div class="post-stats">
                <span><i class="fas fa-thumbs-up"></i> ${post.likes}</span>
                <span><i class="fas fa-thumbs-down"></i> ${post.dislikes}</span>
            </div>`;

        return `
            <div class="post-item">
                <div class="post-content">
                    ${fileContent}
                    <div class="post-text">
                        <div class="post-username">${post.username || 'Anonymous'}</div>
                        <div class="post-text-content collapsed">
                            ${post.content}
                        </div>
                        <button class="read-more-btn">Read More</button>
                    </div>
                    ${categoriesHtml}
                    ${interactionHtml}
                </div>
            </div>
        `;
    }

    function updateCarousel() {
        carouselContainer.innerHTML = '<button class="nav-button prev-button">&lt;</button>';
        
        // Only proceed if we have posts
        if (allPosts.length === 0) {
            carouselContainer.innerHTML = '<p class="text-center">No trending posts available.</p>';
            return;
        }
        
        let indices = [];
        if (allPosts.length === 1) {
            indices = [0];
        } else if (allPosts.length === 2) {
            indices = [0, 1];
        } else {
            indices = [
                (currentIndex - 1 + allPosts.length) % allPosts.length,
                currentIndex,
                (currentIndex + 1) % allPosts.length
            ];
        }

        indices.forEach((index, i) => {
            const postElement = createPostElement(allPosts[index]);
            carouselContainer.insertAdjacentHTML('beforeend', postElement);
        });

        carouselContainer.insertAdjacentHTML('beforeend', '<button class="nav-button next-button">&gt;</button>');

        const posts = document.querySelectorAll('.post-item');
        if (posts.length === 1) {
            posts[0].style.transform = 'translateX(0) scale(1)';
            posts[0].style.opacity = '1';
            posts[0].style.zIndex = '3';
        } else if (posts.length === 2) {
            posts[0].style.transform = 'translateX(-30%) scale(0.9)';
            posts[0].style.opacity = '0.8';
            posts[0].style.zIndex = '2';
            
            posts[1].style.transform = 'translateX(30%) scale(0.9)';
            posts[1].style.opacity = '0.8';
            posts[1].style.zIndex = '2';
        } else if (posts.length === 3) {
            posts[0].style.transform = 'translateX(-60%) scale(0.8)';
            posts[0].style.opacity = '0.6';
            posts[0].style.zIndex = '1';
            
            posts[1].style.transform = 'translateX(0) scale(1)';
            posts[1].style.opacity = '1';
            posts[1].style.zIndex = '3';
            
            posts[2].style.transform = 'translateX(60%) scale(0.8)';
            posts[2].style.opacity = '0.6';
            posts[2].style.zIndex = '1';
        }

        // Reattach event listeners
        const prevButton = document.querySelector('.prev-button');
        const nextButton = document.querySelector('.next-button');
        
        if (prevButton) prevButton.addEventListener('click', rotateLeft);
        if (nextButton) nextButton.addEventListener('click', rotateRight);

        // Initialize Read More for carousel posts
        initializeReadMore();
    }

    function rotateLeft() {
        if (allPosts.length <= 1) return;
        currentIndex = (currentIndex - 1 + allPosts.length) % allPosts.length;
        updateCarousel();
    }

    function rotateRight() {
        if (allPosts.length <= 1) return;
        currentIndex = (currentIndex + 1) % allPosts.length;
        updateCarousel();
    }

    // Auto rotate every 5 seconds if we have enough posts
    let autoRotate;
    if (allPosts.length > 1) {
        autoRotate = setInterval(rotateRight, 5000);
        
        // Pause auto-rotation when hovering over carousel
        carouselContainer.addEventListener('mouseenter', () => {
            clearInterval(autoRotate);
        });

        carouselContainer.addEventListener('mouseleave', () => {
            autoRotate = setInterval(rotateRight, 5000);
        });
    }

    // Initial carousel setup
    if (allPosts.length > 0) {
        updateCarousel();
    } else {
        carouselContainer.innerHTML = '<p class="text-center">No trending posts available.</p>';
    }
});

// Read More functionality for all posts
function initializeReadMore() {
    document.querySelectorAll('.post-text-content').forEach(function(postText) {
        const readMoreBtn = postText.nextElementSibling;
        if (!readMoreBtn || !readMoreBtn.classList.contains('read-more-btn')) return;

        // Reset previous event listeners
        readMoreBtn.replaceWith(readMoreBtn.cloneNode(true));
        const newReadMoreBtn = postText.nextElementSibling;

        // Check if content needs Read More button
        const contentHeight = postText.scrollHeight;
        if (contentHeight <= 100) {
            postText.classList.remove('collapsed');
            newReadMoreBtn.style.display = 'none';
            return;
        }

        // Show Read More button and add click handler
        newReadMoreBtn.style.display = 'block';
        newReadMoreBtn.addEventListener('click', function() {
            const isCollapsed = postText.classList.contains('collapsed');
            
            if (isCollapsed) {
                postText.classList.remove('collapsed');
                postText.classList.add('expanded');
                this.textContent = 'Show Less';
            } else {
                postText.classList.add('collapsed');
                postText.classList.remove('expanded');
                this.textContent = 'Read More';
                
                // Smooth scroll to the top of the post
                postText.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });
}

// Handle interactions for regular posts
function handleInteraction(postId, action) {
    console.log('Handling interaction:', postId, action);
    
    const container = document.querySelector(`.interaction-buttons[data-post-id="${postId}"]`);
    const likeBtn = container.querySelector('.like-btn');
    const dislikeBtn = container.querySelector('.dislike-btn');
    const likeCount = container.querySelector('.like-count');
    const dislikeCount = container.querySelector('.dislike-count');
    
    fetch(`${action}.php?id=${postId}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Update counts
                likeCount.textContent = data.likes;
                dislikeCount.textContent = data.dislikes;
                
                // Update button states
                if (data.userAction === 'like') {
                    likeBtn.classList.remove('btn-primary');
                    likeBtn.classList.add('btn-success');
                    dislikeBtn.classList.remove('btn-danger');
                    dislikeBtn.classList.add('btn-secondary');
                } else if (data.userAction === 'dislike') {
                    likeBtn.classList.remove('btn-success');
                    likeBtn.classList.add('btn-primary');
                    dislikeBtn.classList.remove('btn-secondary');
                    dislikeBtn.classList.add('btn-danger');
                } else {
                    // Reset both buttons
                    likeBtn.classList.remove('btn-success');
                    likeBtn.classList.add('btn-primary');
                    dislikeBtn.classList.remove('btn-danger');
                    dislikeBtn.classList.add('btn-secondary');
                }
            } else {
                console.error('Error:', data.message);
                alert(data.message || 'Failed to update interaction');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to update interaction. Please try again.');
        });
}

function handleNotInterested(postId) {
    event.preventDefault();
    
    const postCard = document.querySelector(`.post-card[data-post-id="${postId}"]`);
    if (!postCard) return;

    // Add fade out animation
    postCard.classList.add('fade-out');
    
    fetch(`not_interested.php?id=${postId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            // Remove the post after animation
            setTimeout(() => {
                postCard.remove();
            }, 300);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to hide post. Please try again.');
            postCard.classList.remove('fade-out'); // Revert animation if failed
        });
}

// Initialize Read More functionality and other events
document.addEventListener('DOMContentLoaded', function() {
    initializeReadMore();
    
    // Auto-submit the form when checkboxes are changed
    const form = document.getElementById('categoryFilterForm');
    if (form) {
        const checkboxes = form.querySelectorAll('.category-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                form.submit();
            });
        });
    }
});

// Comment functions
// Toggle comments visibility
document.addEventListener('click', function(e) {
    if (e.target.matches('.toggle-comments') || e.target.closest('.toggle-comments')) {
        const button = e.target.matches('.toggle-comments') ? e.target : e.target.closest('.toggle-comments');
        const postId = button.getAttribute('data-post-id');
        const commentSection = document.querySelector(`.comments-section[data-post-id="${postId}"]`);
        const commentsList = document.getElementById(`comments-${postId}`);
        const commentForm = commentSection.querySelector('.comment-form');
        const paginationSection = document.getElementById(`pagination-${postId}`);
        
        if (commentsList.style.display === 'none') {
            // Show comments
            commentsList.style.display = 'block';
            commentForm.style.display = 'block';
            button.innerHTML = '<i class="fas fa-chevron-up"></i> Sembunyikan Komentar';
            
            // Load comments if not loaded yet
            if (commentsList.getAttribute('data-loaded') !== 'true') {
                loadComments(postId, 1);
            }
        } else {
            // Hide comments
            commentsList.style.display = 'none';
            commentForm.style.display = 'none';
            paginationSection.style.display = 'none';
            button.innerHTML = '<i class="fas fa-chevron-down"></i> Tampilkan Komentar';
        }
    }
});

// Fungsi untuk toggle komentar dari tombol di card actions
function toggleComments(postId) {
    const button = document.querySelector(`.toggle-comments[data-post-id="${postId}"]`);
    if (button) {
        button.click();
    }
}

// Load comments function
function loadComments(postId, page = 1) {
    const commentsList = document.getElementById(`comments-${postId}`);
    const paginationSection = document.getElementById(`pagination-${postId}`);
    const loadMoreBtn = paginationSection ? paginationSection.querySelector('.load-more-comments') : null;
    
    // Show loading indicator during fetch
    if (page === 1) {
        commentsList.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ms-2">Memuat komentar...</span>
            </div>
        `;
    }
    
    fetch(`get_comments.php?post_id=${postId}&page=${page}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response error: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            // Remove loading indicator on first load
            if (page === 1) {
                commentsList.innerHTML = '';
            }
            
            if (data.success) {
                if (data.comments.length === 0 && page === 1) {
                    // No comments yet
                    commentsList.innerHTML = `
                        <div class="empty-comments">
                            Belum ada komentar. Jadilah yang pertama berkomentar!
                        </div>
                    `;
                    if (paginationSection) {
                        paginationSection.style.display = 'none';
                    }
                } else {
                    // Append comments
                    data.comments.forEach(comment => {
                        const commentItem = createCommentElement(comment);
                        commentsList.appendChild(commentItem);
                    });
                    
                    // Update pagination
                    if (paginationSection && loadMoreBtn) {
                        if (data.pagination.has_more) {
                            paginationSection.style.display = 'block';
                            loadMoreBtn.setAttribute('data-page', page + 1);
                            loadMoreBtn.disabled = false;
                        } else {
                            paginationSection.style.display = 'none';
                        }
                    }
                    
                    // Mark as loaded
                    commentsList.setAttribute('data-loaded', 'true');
                }
            } else {
                commentsList.innerHTML = `
                    <div class="alert alert-danger">
                        <p>Error: ${data.message || 'Failed to load comments'}</p>
                        <p>Error Code: ${data.error_code || 'Unknown'}</p>
                        <p class="mb-0">Please try again or contact support if the issue persists.</p>
                    </div>
                `;
                console.error("Comment loading error:", data);
            }
        })
        .catch(error => {
            commentsList.innerHTML = `
                <div class="alert alert-danger">
                    <p>Error: ${error.message || 'An error occurred while loading comments'}</p>
                    <p class="mb-0">Please check your connection and try again.</p>
                </div>
            `;
            console.error("Comment fetch error:", error);
        });
}

// Create comment element
function createCommentElement(comment) {
    const div = document.createElement('div');
    div.className = 'comment-item';
    div.setAttribute('data-comment-id', comment.id);
    
    let avatarHtml = '';
    if (comment.profile_pic) {
        avatarHtml = `<img src="${comment.profile_pic}" class="comment-avatar" alt="${comment.display_name}">`;
    } else {
        avatarHtml = `<div class="comment-avatar d-flex justify-content-center align-items-center">
            <i class="fas fa-user text-secondary"></i>
        </div>`;
    }
    
    let deleteButton = '';
    if (comment.can_delete) {
        deleteButton = `
            <div class="comment-actions">
                <button onclick="deleteComment(${comment.id})">
                    <i class="fas fa-trash-alt"></i> Hapus
                </button>
            </div>
        `;
    }
    
    div.innerHTML = `
        <div class="comment-header">
            ${avatarHtml}
            <div>
                <div class="comment-username">${comment.display_name}</div>
                <div class="comment-time">${comment.time_ago}</div>
            </div>
        </div>
        <div class="comment-content">${comment.content}</div>
        ${deleteButton}
    `;
    
    return div;
}

// Add comment function
function addComment(event, postId) {
    event.preventDefault();
    
    const commentSection = document.querySelector(`.comments-section[data-post-id="${postId}"]`);
    const input = commentSection.querySelector('.comment-input');
    const content = input.value.trim();
    
    if (!content) {
        return false;
    }
    
    // Disable input and show loading state
    input.disabled = true;
    const submitBtn = commentSection.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
    submitBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('content', content);
    
    fetch('add_comment.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response error: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            // Reset form state
            input.disabled = false;
            input.focus();
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            
            if (data.success) {
                // Clear input
                input.value = '';
                
                // Add new comment to the top of the list
                const commentsList = document.getElementById(`comments-${postId}`);
                const emptyMessage = commentsList.querySelector('.empty-comments');
                
                if (emptyMessage) {
                    commentsList.innerHTML = '';
                }
                
                const commentElement = createCommentElement(data.comment);
                commentsList.insertBefore(commentElement, commentsList.firstChild);
                
                // Update comment count
                const commentCountEl = document.querySelector(`.comment-count[data-post-id="${postId}"]`);
                if (commentCountEl) {
                    const currentCount = parseInt(commentCountEl.textContent);
                    commentCountEl.textContent = currentCount + 1;
                }
            } else {
                console.error("Error adding comment:", data);
                alert(data.message || 'Failed to add comment');
            }
        })
        .catch(error => {
            // Reset form state
            input.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            
            console.error("Comment submission error:", error);
            alert('Error: ' + (error.message || 'An error occurred'));
        });
    
    return false;
}

// Delete comment function
function deleteComment(commentId) {
    if (!confirm('Apakah Anda yakin ingin menghapus komentar ini?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('comment_id', commentId);
    
    fetch('delete_comment.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response error: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Remove comment from DOM
                const commentElement = document.querySelector(`.comment-item[data-comment-id="${commentId}"]`);
                const postId = commentElement.closest('.comments-section').getAttribute('data-post-id');
                commentElement.remove();
                
                // Update comment count
                const commentCountEl = document.querySelector(`.comment-count[data-post-id="${postId}"]`);
                if (commentCountEl) {
                    const currentCount = parseInt(commentCountEl.textContent);
                    commentCountEl.textContent = Math.max(0, currentCount - 1);
                }
                
                // Show empty message if no comments left
                const commentsList = document.getElementById(`comments-${postId}`);
                if (commentsList.children.length === 0) {
                    commentsList.innerHTML = `
                        <div class="empty-comments">
                            Belum ada komentar. Jadilah yang pertama berkomentar!
                        </div>
                    `;
                }
            } else {
                console.error("Error deleting comment:", data);
                alert(data.message || 'Failed to delete comment');
            }
        })
        .catch(error => {
            console.error("Comment deletion error:", error);
            alert('Error: ' + (error.message || 'An error occurred'));
        });
}

// Load more comments
document.addEventListener('click', function(e) {
    if (e.target.matches('.load-more-comments') || e.target.closest('.load-more-comments')) {
        const button = e.target.matches('.load-more-comments') ? e.target : e.target.closest('.load-more-comments');
        const postId = button.getAttribute('data-post-id');
        const page = parseInt(button.getAttribute('data-page'));
        
        // Add loading indicator to button
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
        button.disabled = true;
        
        loadComments(postId, page);
        
        // Reset button after some delay
        setTimeout(() => {
            button.innerHTML = originalText;
            // Note: Button will be re-enabled in loadComments only if there are more pages
        }, 1000);
    }
});
