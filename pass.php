<?php
echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]);