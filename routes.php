<?php declare(strict_types=1);

$router->get("/", fn() => Response::render("tes"));
$router->get("/ping", fn() => Response::json(["message" => "pong"]));
