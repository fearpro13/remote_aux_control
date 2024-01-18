FROM composer:2.0.8

WORKDIR /app

COPY entrypoint.sh .

CMD ["bash","./entrypoint.sh"]