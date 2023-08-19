FROM ubuntu:latest
RUN apt update &&\
    apt -y install nginx &&\
    DEBIAN_FRONTEND=noninteractive apt -y install php php-cli php-curl php-opcache php-fpm php-gd php-mysqlnd php-soap php-mbstring php-ldap php-xml php-imap php-zip &&\
    apt -y install supervisor
    # apt -y install php php-cli php-curl php-opcache php-fpm php-gd php-mysqlnd php-soap php-mbstring php-ldap php-xml php-imap php-zip
COPY /docker-conf-files/nginx.conf /etc/nginx/conf.d/processmaker.conf
COPY /docker-conf-files/fpm.conf /etc/php/8.1/fpm/pool.d/processmaker.conf
COPY /docker-conf-files/custom.ini /etc/php/8.1/fpm/conf.d/
COPY /docker-conf-files/worker.conf /etc/supervisor/conf.d/laravel-worker-workflow.conf
WORKDIR /opt/processmaker
COPY . .

ENTRYPOINT ["sh", "./entrypoint.sh" ]

EXPOSE 80   
