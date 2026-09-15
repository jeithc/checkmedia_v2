<?php

use App\Services\Advisual\AdvisualConnector;

it('interpolates bindings into the sql, quoting strings and skipping literals', function () {
    $connector = new AdvisualConnector;

    $sql = $connector->interpolate(
        "SELECT * FROM Espacio WHERE EspacioCodigo = ? AND Nota = 'no soy un ? placeholder' AND X = ? AND Y = ? AND Z = ?",
        ['17766', "O'Brien", 42, null]
    );

    expect($sql)->toBe(
        "SELECT * FROM Espacio WHERE EspacioCodigo = N'17766' AND Nota = 'no soy un ? placeholder' AND X = N'O''Brien' AND Y = 42 AND Z = NULL"
    );
});

it('fails loudly when there are fewer bindings than placeholders', function () {
    (new AdvisualConnector)->interpolate('SELECT ?, ?', ['solo uno']);
})->throws(InvalidArgumentException::class);
