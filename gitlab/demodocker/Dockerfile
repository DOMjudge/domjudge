FROM domjudge/gitlabci:2.1

ARG DJ_SRC
ARG DJ_ETC

ENV DEBIAN_FRONTEND=noninteractive

# Use the current PR sources as if its a released version
RUN mkdir $DJ_SRC
COPY domjudge $DJ_SRC/
#RUN sudo -u domjudge ./gitlab/demodocker/rebase.sh
#    - git fetch
#    - git config --global user.email "never@commits.ci"
#    - git config --global user.name "Rebase against main"
#    - echo -e "section_start:`date +%s`:diff[collapsed=true]\r\e[0K'Git Diff'"
#    - git diff origin/main
#    - echo -e "section_end:`date +%s`:diff\r\e[0K"
#    - git pull origin main
#
RUN chown -R domjudge $DJ_SRC

WORKDIR $DJ_SRC
RUN sudo -u domjudge ./gitlab/demodocker/configure.sh

# Expose HTTP port
EXPOSE 80

CMD ["/scripts/start.sh"]
