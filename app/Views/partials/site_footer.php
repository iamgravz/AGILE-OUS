<?php /** The public footer. */ ?>
    <footer class="site-footer">
        <div class="container">
            <div class="site-footer__grid">
                <div>
                    <h2 class="site-footer__heading">Organization</h2>
                    <p>AGILE-OUS, PUP Open University System</p>
                    <?php /* Constitution, Article I Section 3. The full address is on the About page. */ ?>
                    <p>NALLRC, Sta. Mesa, Manila</p>
                </div>
                <div>
                    <h2 class="site-footer__heading">Contact</h2>
                    <?php /* From .env (ORG_*), not Settings: error pages use this footer and must render with the database down. */ ?>
                    <div class="site-footer__contact">
                        <p><a href="mailto:<?= e(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a></p>
                        <?php if (config('app.msw_email') !== '') : ?>
                            <p><abbr title="Membership and Student Welfare Committee">MSW</abbr>: <a href="mailto:<?= e(config('app.msw_email')) ?>"><?= e(config('app.msw_email')) ?></a></p>
                        <?php endif; ?>
                        <?php if (config('app.facebook_url') !== '') : ?>
                            <p><a href="<?= e(config('app.facebook_url')) ?>" rel="noopener" title="AGILE OUS on Facebook">Facebook</a></p>
                        <?php endif; ?>
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
