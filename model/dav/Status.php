<?php

/**
 * Class Dav_Status
 */
class Dav_Status
{
    public static $Msg = [
        '200' => 'HTTP/1.1 200 OK',
        '201' => 'HTTP/1.1 201 Created',
        '202' => 'HTTP/1.1 202 Accepted',
        '203' => 'HTTP/1.1 203 Non-Authoritative Information',
        '204' => 'HTTP/1.1 204 No Content',
        '205' => 'HTTP/1.1 205 Reset Content',
        '206' => 'HTTP/1.1 206 Partial Content',
        '207' => 'HTTP/1.1 207 Multi-Status',
        '300' => 'HTTP/1.1 300 Multiple Choices',
        '301' => 'HTTP/1.1 301 Moved Permanently',
        '302' => 'HTTP/1.1 302 Moved Temporarily',
        '303' => 'HTTP/1.1 303 See Other',
        '304' => 'HTTP/1.1 304 Not Modified',
        '305' => 'HTTP/1.1 305 Use Proxy',
        '307' => 'HTTP/1.1 307 Temporary Redirect ',
        '400' => 'HTTP/1.1 400 Bad Request',
        '401' => 'HTTP/1.1 401 Unauthorized',
        '402' => 'HTTP/1.1 402 Payment Required',
        '403' => 'HTTP/1.1 403 Forbidden',
        '404' => 'HTTP/1.1 404 Not Found',
        '405' => 'HTTP/1.1 405 Method Not Allowed',
        '406' => 'HTTP/1.1 406 Not Acceptable',
        '407' => 'HTTP/1.1 407 Proxy Authentication Required',
        '408' => 'HTTP/1.1 408 Request Timeout',
        '409' => 'HTTP/1.1 409 Conflict',
        '410' => 'HTTP/1.1 410 Gone',
        '411' => 'HTTP/1.1 411 Length Required',
        '412' => 'HTTP/1.1 412 Precondition Failed',
        '413' => 'HTTP/1.1 413 Request Entity Too Large',
        '414' => 'HTTP/1.1 414 Request URI Too Large',
        '415' => 'HTTP/1.1 415 Unsupported Media Type',
        '416' => 'HTTP/1.1 416 Requested Range Not Satisfiable',
        '417' => 'HTTP/1.1 417 Expectation Failed',
        '422' => 'HTTP/1.1 422 Unprocessable Entity',
        '423' => 'HTTP/1.1 423 Locked',
        '424' => 'HTTP/1.1 424 Failed Dependency',
        '425' => 'HTTP/1.1 425 Insufficient Space on Resource',
        '500' => 'HTTP/1.1 500 Internal Server Error',
        '501' => 'HTTP/1.1 501 Not Implemented',
        '503' => 'HTTP/1.1 503 Service Unavailable',
        '505' => 'HTTP/1.1 505 HTTP Version not supported',
        '507' => 'HTTP/1.1 507 Insufficient Storage',
    ];
}
