<?php declare(strict_types=1);

$router->get("/", fn() => Response::render("tes"));
$router->all("/ping", fn() => Response::text("pong\r\n"));
