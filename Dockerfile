FROM ubuntu:latest
RUN apt update &&\
    apt -y install nginx &&\
    DEBIAN_FRONTEND=noninteractive apt -y install php php-cli php-curl php-opcache php-fpm php-gd php-mysqlnd php-soap php-mbstring php-ldap php-xml php-imap php-zip
    # apt -y install php php-cli php-curl php-opcache php-fpm php-gd php-mysqlnd php-soap php-mbstring php-ldap php-xml php-imap php-zip
COPY /docker-conf-files/nginx.conf /etc/nginx/conf.d/processmaker.conf
COPY /docker-conf-files/fpm.conf /etc/php/8.1/fpm/pool.d/processmaker.conf
COPY /docker-conf-files/custom.ini /etc/php/8.1/fpm/conf.d/
WORKDIR /opt/processmaker
COPY --chown=www-data:www-data . .

COPY entrypoint.sh ./

# Start and enable SSH
RUN apt-get update \
    && apt-get install -y --no-install-recommends dialog \
    && apt-get install -y --no-install-recommends openssh-server \
    && echo "root:Docker!" | chpasswd \
    && chmod u+x ./entrypoint.sh
COPY sshd_config /etc/ssh/

ENTRYPOINT [ "./entrypoint.sh" ]

EXPOSE 80 2222
