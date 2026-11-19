<?php /** The public footer. */ ?>
    <footer class="site-footer">
        <div class="container">
            <div class="site-footer__grid">
                <div>
                    <h2 class="site-footer__heading">Organization</h2>
                    <p>Academic Guild of IT Learners and Experts</p>
                    <p>PUP Open University System</p>
                </div>
                <div>
                    <h2 class="site-footer__heading">Contact</h2>
                    <?php /* TODO: replace with the PUP OUS street address and the real Facebook page link. */ ?>
                    <div class="site-footer__contact">
                        <p>TODO: PUP OUS address</p>
                        <p><a href="mailto:<?= e(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a></p>
                        <p>TODO: Facebook page link</p>
                    </div>
                </div>
                <div>
                    <h2 class="site-footer__heading">Legal</h2>
                    <p><a href="/privacy">Privacy Notice</a></p>
                    <p class="site-footer__legal">&copy; <?= e(date('Y')) ?> AGILE-OUS</p>
                </div>
            </div>
        </div>
    </footer>
