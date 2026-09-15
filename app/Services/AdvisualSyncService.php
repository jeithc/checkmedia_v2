<?php

namespace App\Services;

use App\Exceptions\AdvisualUnavailableException;
use App\Models\AdvertisingSpace;
use App\Models\CommercialBooking;
use App\Services\Advisual\AdvisualConnector;
use Exception;
use Illuminate\Support\Facades\Log;

class AdvisualSyncService
{
    protected AdvisualConnector $connector;

    public function __construct(?AdvisualConnector $connector = null)
    {
        $this->connector = $connector ?? new AdvisualConnector;
    }

    /**
     * Fetch space data from external SQL Server and sync to local DB.
     * Replaces legacy logic from buscardata2.php
     *
     * @param  string  $code  EspacioCodigo
     * @return AdvertisingSpace|null
     */
    public function syncSpaceByCcde(string $code)
    {
        try {
            $sqlQuery = '
                SELECT TOP 1 
                    ElementoCodigo,
                    EspacioCodigo,
                    ProveedorNombre,
                    EspacioPropio,
                    TipoElementoNombre,
                    EspacioCodigoPlano,
                    IluminacionNombre,
                    CiudadNombre,
                    ProductoNombre,
                    LocacionNombre,
                    LocalizacionNombre,
                    EspacioUbicacion,
                    clientenombre
                FROM Espacio as es 
                INNER JOIN elemento as ele ON (es.EspacioElementoCodigo=ele.ElementoCodigo)
                LEFT JOIN Locacion as L ON (es.EspacioLocacionCodigo=L.LocacionCodigo)
                LEFT JOIN Ciudad as ciu ON (L.CiudadCodigo=ciu.CiudadCodigo)
                LEFT JOIN Proveedor as P ON (es.EspacioProveedorCodigo=P.ProveedorCodigo)
                LEFT JOIN Localizacion as Lo ON (es.EspacioLocalizacionCodigo=Lo.LocalizacionCodigo)
                LEFT JOIN tipoelemento as tipoe ON (es.EspacioTipoElementoCodigo=tipoe.TipoElementoCodigo)
                LEFT JOIN Iluminacion as I ON (es.EspacioIluminacionCod=I.IluminacionCodigo)
                LEFT JOIN Producto as pr ON (L.ProductoCodigo=pr.ProductoCodigo)
                LEFT JOIN pedido as ped on es.espaciopedidocodigo=ped.pedidocodigo
                LEFT JOIN Negocio as n on n.NegocioCodigo=ped.negociocodigo
                LEFT JOIN cliente as cl on n.NegocioClienteCodigo=cl.ClienteCodigo
                WHERE EspacioCodigo = ?
            ';

            // ODBC (Hostinger) con fallback nativo lo resuelve el connector.
            // Un fallo de conexión se propaga: "no existe" y "no pude preguntar"
            // son cosas distintas para quien llama.
            try {
                $row = $this->connector->selectOne($sqlQuery, [$code]);
            } catch (\Throwable $e) {
                Log::error("Advisual Sync Error for code $code: ".$e->getMessage());

                throw new AdvisualUnavailableException('No se pudo consultar Advisual: '.$e->getMessage(), 0, $e);
            }

            if (! $row) {
                return null;
            }

            // 2. Map & Update/Create Physical Space
            // In legacy: INSERT INTO elemento ...
            $space = AdvertisingSpace::updateOrCreate(
                ['external_code' => $row->EspacioCodigo],
                [
                    'provider' => $row->ProveedorNombre,
                    'type' => $row->TipoElementoNombre,
                    'category' => $row->ProductoNombre,
                    'ownership' => $row->EspacioPropio,
                    'illumination_type' => $row->IluminacionNombre,
                    'city' => $row->CiudadNombre,
                    'location_name' => $row->LocacionNombre,
                    'address' => $row->EspacioUbicacion,
                    'zone' => $row->LocalizacionNombre,
                    // 'latitude' => ... // Not present in legacy query
                    // 'longitude' => ...
                ]
            );

            // 3. Map & Update/Create Commercial Booking
            // In legacy: INSERT INTO espacio_elemento (Semana Actual...)

            $now = now();
            $weekData = \App\Models\Audit::getCalendarYearAndWeek($now);

            CommercialBooking::updateOrCreate(
                [
                    'advertising_space_id' => $space->id,
                    'year' => $weekData['year'],
                    'week' => $weekData['week'],
                ],
                [
                    'client_name' => $row->clientenombre ?? 'SIN CLIENTE',
                    'contract_code' => $row->EspacioCodigoPlano,
                    'product_name' => $row->ProductoNombre,
                ]
            );

            return $space;

        } catch (AdvisualUnavailableException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error("Advisual Sync mapping error for code $code: ".$e->getMessage());

            // Fail gracefully? Or rethrow?
            // For now, return null so UI handles it as "Not Found in Remote" or "Connection Error"
            return null;
        }
    }
}
