<?php
// partials/footer.php
?>
    </main>
    <!-- Main Content End -->
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <div class="footer-logo">
                        <i class="fas fa-bolt"></i> <?php echo SITE_NAME; ?>
                    </div>
                    <p>Your trusted platform for instant airtime, data, and bill payments. Fast, secure, and reliable.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo url('index.php#features'); ?>"><i class="fas fa-chevron-right"></i> Features</a></li>
                        <li><a href="<?php echo url('index.php#services'); ?>"><i class="fas fa-chevron-right"></i> Services</a></li>
                        <li><a href="<?php echo url('index.php#pricing'); ?>"><i class="fas fa-chevron-right"></i> Pricing</a></li>
                        <li><a href="<?php echo url('faq.php'); ?>"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="<?php echo url('contact.php'); ?>"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                        <li><a href="<?php echo url('privacy.php'); ?>"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                        <li><a href="<?php echo url('terms.php'); ?>"><i class="fas fa-chevron-right"></i> Terms of Service</a></li>
                        <li><a href="<?php echo url('refund.php'); ?>"><i class="fas fa-chevron-right"></i> Refund Policy</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h4>Contact Info</h4>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo SITE_EMAIL; ?></a></p>
                    <p><i class="fas fa-phone-alt"></i> <a href="tel:<?php echo SITE_PHONE; ?>"><?php echo SITE_PHONE; ?></a></p>
                    <p><i class="fas fa-map-marker-alt"></i> Lagos, Nigeria</p>
                    <p><i class="fas fa-clock"></i> Mon - Sat: 24/7 Support</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                    <div class="footer-bottom-links">
                        <a href="<?php echo url('privacy.php'); ?>">Privacy</a>
                        <a href="<?php echo url('terms.php'); ?>">Terms</a>
                        <a href="<?php echo url('sitemap.php'); ?>">Sitemap</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Mobile Menu JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('show');
                const icon = this.querySelector('i');
                if (mobileMenu.classList.contains('show')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (mobileMenu && mobileMenu.classList.contains('show')) {
                if (!mobileMenu.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                    mobileMenu.classList.remove('show');
                    const icon = mobileMenuBtn.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    });
    </script>
    
    <!-- Main JavaScript -->
    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>