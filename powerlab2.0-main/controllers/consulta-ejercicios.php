<?php
// Biblioteca para el deportista en JSON (buscador en vivo y scroll infinito).
// Solo devuelve ejercicios ACTIVOS.
include_once "sesion-deportista.php";
requerirRolJson("deportista");

include_once "../models/ejercicios.php";

const EJERCICIOS_POR_PAGINA = 24;

$filtros = [
    "q" => mb_substr(trim((string) ($_GET["q"] ?? "")), 0, 100),
    "grupo" => (int) ($_GET["grupo"] ?? 0),
    "equipo" => (int) ($_GET["equipo"] ?? 0),
    "nivel" => (int) ($_GET["nivel"] ?? 0),
    "estado" => "activos",
];
$pagina = max(1, (int) ($_GET["pagina"] ?? 1));

$resultado = (new ejercicios())->buscar($filtros, $pagina, EJERCICIOS_POR_PAGINA);

responderJson([
    "ok" => true,
    "total" => $resultado["total"],
    "pagina" => $resultado["pagina"],
    "paginas" => $resultado["paginas"],
    "items" => array_map([ejercicios::class, "tarjeta"], $resultado["items"]),
]);
