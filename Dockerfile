FROM ubuntu:latest

WORKDIR /home/ubuntu

RUN apt-get update && apt-get install -y python3 python3-pip openssh-server sudo software-properties-common systemd
RUN add-apt-repository --yes --update ppa:ansible/ansible && apt-get install -y ansible
RUN mkdir -p ./ansible-flarum
COPY . ./ansible-flarum

VOLUME ["/sys/fs/cgroup"]
CMD ["/sbin/init"]
