server {
    listen       8443;
    server_name  {server_name};

    access_log                    {base_root}/logs/nginx/access.log  main;
    charset                       utf-8;
    sendfile                      on;
    tcp_nodelay                   on;
    client_max_body_size          0;
    client_body_in_file_only      clean;
    client_body_in_single_buffer  on;

    location / {
        root                          {base_root}/interface;
        rewrite                       .*  /index.php break;
        fastcgi_pass                  unix:{base_root}/server/run/php-cgi.sock;
        fastcgi_keep_conn             on;
        fastcgi_limit_rate            0;
        fastcgi_request_buffering     on;
        fastcgi_cache_revalidate      on;
        fastcgi_pass_request_headers  on;
        fastcgi_force_ranges          on;
        fastcgi_connect_timeout       600s;
        fastcgi_read_timeout          600s;
        include                       fastcgi.conf;
    }
}