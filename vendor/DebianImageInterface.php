<?php

interface DebianImageInterface extends BaseImageInterface
{
    public function apt($package);
}
