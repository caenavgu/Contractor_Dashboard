<?php
// public/views/partials/footer.php
declare(strict_types=1);
?>
        <footer class="bg-dark text-white py-4">
            <div class="container">
                <div class="row">
                    <!-- Primera Parte -->
                    <div class="col-md-3 col-sm-12">
                        <img src="assets/img/everwell-air-conitioning-white.svg" alt="Everwell Parts, Inc." width="250" >
                        <p style="font-weight: bold; color: #ffffff; margin-bottom: 0px; line-height : 20px;">Because air-conditioning is more than a luxury, it's a necessity.</p>
                        <p style="font-weight: bold; color: #ed1c24; margin-bottom: 0px; line-height : 20px;">#FeelEverwell!</p>
                    </div>
                    <!-- Segunda Parte -->
                    <div class="col-md-3 col-sm-12 text-center d-none d-sm-flex">
                        <h5></h5>
                        <ul class="list-unstyled">
                            <li><a href="#" class="text-white"></a></li>
                            <li><a href="#" class="text-white"></a></li>
                            <li><a href="#" class="text-white"></a></li>
                        </ul>
                    </div>
                    <!-- Tercera Parte -->
                    <div class="col-md-3 col-sm-12 text-center d-none d-sm-flex">
                        <h5></h5>
                        <p></p>
                        <p></p>
                        <p></p>
                    </div>
                    <!-- Cuarta Parte -->
                    <div class="col-md-3 col-sm-12 text-center d-none d-sm-flex">
                        <h5></h5>
                        <p></p>
                        <p></p>
                        <p></p>
                    </div>
                </div>
            </div>
        </footer>
        <div style="padding-top: 2%; padding-bottom: 2%;" class="container-fluid bg-dark copyright">
            <div class="row justify-content-md-center">
                <div class="col-md-4 col-sm-12 text-center">
                    <strong style="color: white;"> © <?php echo date("Y"); ?> <a style="color: white;" href="https://www.everwellparts.com/">Everwell Parts Inc.</a></strong>
                </div>  
                    <div class="col-md-auto">
                    Variable width content
                    </div>          
                <div class="col-md-4 col-sm-12 text-center">
                    <strong><a  style="color: white;" href="https://everwellparts.com/terms-conditions/" aria-current="page">Terms &amp; Conditions</a></strong>
                </div>
            </div>
        </div>
        <!-- <script src="<?= asset_url('/js/bootstrap.bundle.min.js'); ?>"></script> -->
    </body>
</html>
