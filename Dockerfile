FROM i386/debian:8-slim

# Force Debian 8 installation (if needed)
RUN echo "deb http://archive.debian.org/debian jessie main contrib non-free" > /etc/apt/sources.list && \
     echo "deb http://archive.debian.org/debian-security jessie/updates main" >> /etc/apt/sources.list && \
     apt-get update -o Acquire::Check-Valid-Until=false -y

# Install dependencies with the correct versions
RUN apt-get install --force-yes  --no-install-recommends \
    wget curl unzip libc6 libstdc++6 make ca-certificates build-essential gcc-4.9 cpp-4.9 -y && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Create user
RUN groupadd -r hlds && useradd --no-log-init --system --create-home --home-dir /server --gid hlds hlds
USER hlds

# Download all necessary files
RUN curl -L -o /tmp/hlds_l_3109_full.tar.gz https://archive.org/download/hlds_l_3111_full/hlds_l_3109_full.tar.gz
#COPY files/hlds_l_3109_full.tar.gz /tmp/hlds_l_3109_full.tar.gz
RUN curl -L -o /tmp/cs_13_full.tar.gz https://archive.org/download/hlds_l_3111_full_202503/cs_13_full.tar.gz
#COPY files/cs_13_full.tar.gz /tmp/cs_13_full.tar.gz
RUN curl -L -o /tmp/cs_15_full.tar.gz https://archive.org/download/hlds_l_3111_full_202503/cs_15_full.tar.gz
#COPY files/cs_15_full.tar.gz /tmp/cs_15_full.tar.gz
RUN curl -L -o /tmp/amxmodx-base.tar.gz https://www.amxmodx.org/release/amxmodx-1.8.2-base-linux.tar.gz
#COPY files/amxmodx-base.tar.gz /tmp/amxmodx-base.tar.gz
RUN curl -L -o /tmp/amxmodx-cstrike.tar.gz https://www.amxmodx.org/release/amxmodx-1.8.2-cstrike-linux.tar.gz
#COPY files/amxmodx-base.tar.gz /tmp/amxmodx-cstrike.tar.gz
RUN curl -L -o /tmp/podbot_full_V3B22.zip https://archive.org/download/hlds_l_3111_full_202503/podbot_full_V3B22.zip
#COPY files/podbot_full_V3B22.zip /tmp/podbot_full_V3B22.zip
#RUN curl -L -o /tmp/metamod.tar.gz "https://sourceforge.net/projects/metamod/files/Metamod%20Binaries/1.20/metamod-1.20-linux.tar.gz/download"
#COPY files/metamod.tar.gz /tmp/metamod.tar.gz

# Download and extract HLDS
RUN mkdir -p /server/hlds_l/ && \
    tar -xzf /tmp/hlds_l_3109_full.tar.gz -C /server/
	
# Download and install Counter-Strike 1.3
RUN tar -xzf /tmp/cs_13_full.tar.gz -C /server/hlds_l/
	
# Download and install Counter-Strike 1.5
RUN mkdir -p /server/hlds_l/cstrk15 && \
    tar -xzf /tmp/cs_15_full.tar.gz -C /server/hlds_l/cstrk15 && \
    mv /server/hlds_l/cstrk15/cstrike/* /server/hlds_l/cstrk15/ && \
    rm -rf /server/hlds_l/cstrk15/cstrike

# Install Metamod
#RUN mkdir -p /server/hlds_l/cstrike/addons/metamod
#    tar -xzf /tmp/metamod.tar.gz -C /server/hlds_l/cstrike/addons/metamod/dlls && \
#    sed -i '/^gamedll_linux/s/^/\/\//' /server/hlds_l/cstrike/liblist.gam && \
RUN  echo 'gamedll_linux "addons/metamod/dlls/metamod_i386.so"' >> /server/hlds_l/cstrike/liblist.gam
	
# AMXModX 1.9
RUN tar -zxvf /tmp/amxmodx-base.tar.gz -C /server/hlds_l/cstrike && \
    tar -zxvf /tmp/amxmodx-cstrike.tar.gz -C /server/hlds_l/cstrike
#   echo "linux addons/amxmodx/dlls/amxmodx_mm_i386.so" >> /server/hlds_l/cstrike/addons/metamod/plugins.ini

# Install Podbot (Ensure correct extraction path)
RUN mkdir -p /server/hlds_l/cstrike/addons/podbot/ && \
    mkdir -p /server/hlds_l/cstrk15/addons/podbot/ && \
    unzip -o /tmp/podbot_full_V3B22.zip -d /server/hlds_l/cstrike/addons/ && \
    unzip -o /tmp/podbot_full_V3B22.zip -d /server/hlds_l/cstrk15/addons/
#   echo "linux addons/podbot/podbot_mm_i386.so" >> /server/hlds_l/cstrike/addons/metamod/plugins.ini
	
#RUN cp -r -f /server/hlds_l/cstrike/addons /server/hlds_l/cstrk15/
RUN cp -f /server/hlds_l/cstrike/liblist.gam /server/hlds_l/cstrk15/liblist.gam

#CS 1.3/1.5 PATCH
RUN mkdir -p /server/hlds_l/cstrike/addons/
RUN mkdir -p /server/hlds_l/cstrk15/addons/
COPY config/cs13addons /server/hlds_l/cstrike/addons/
COPY config/cs15addons /server/hlds_l/cstrk15/addons/
COPY config/cs_cfg /server/hlds_l/cstrk15/
COPY config/cs_cfg /server/hlds_l/cstrike/

USER root
# Remove unnecessary mod folders
RUN rm -rf /server/hlds_l/tfc /server/hlds_l/dmc /server/hlds_l/ricochet  /server/hlds_l/cstrk15/cstrike /tmp/

WORKDIR /server/hlds_l/

# Install WON2Fixes and modified HLDS_RUN
USER root
# Create and overwrite woncomm.lst & valvecomm.lst
RUN echo "\
Titan\n\
{\n\
\ttitan.won2.steamlessproject.nl:6003\n\
\tcs.techpinoy.net:6003\n\
}\n\
\n\
Auth\n\
{\n\
\t// As for now (Oct 2005) this server isn't online yet.\n\
\tauth.won2.steamlessproject.nl:7002\n\
}\n\
\n\
Master\n\
{\n\
\tmaster.won2.steamlessproject.nl:27010\n\
\tmaster4.won2.steamlessproject.nl:27010\n\
\tmaster2.won2.steamlessproject.nl:27010\n\
\tmaster3.won2.steamlessproject.nl:27010\n\
\thlmaster.order-of-phalanx.net:27010\n\
\thlmaster2.order-of-phalanx.net:27010\n\
\thlmaster3.order-of-phalanx.net:27010\n\
\tcs.techpinoy.net:27010\n\
}\n\
\n\
ModServer\n\
{\n\
\tmods.won2.steamlessproject.nl:27011\n\
\tcs.techpinoy.net:27011\n\
}\n\
\n\
Secure\n\
{\n\
\thlauth.won2.steamlessproject.nl:27012\n\
\thlauth2.won2.steamlessproject.nl:27012\n\
\thlauth3.won2.steamlessproject.nl:27012\n\
}" | tee /server/hlds_l/valve/woncomm.lst /server/hlds_l/valve/valvecomm.lst > /dev/null


#COPY config ./
#COPY config/cstrike ./cstrk15/
RUN chmod +x /server/hlds_l/hlds*

# Modify HLDS_RUN for WON2
RUN echo 'int NET_IsReservedAdr(){return 1;}' > /server/hlds_l/nowon.c && \
    gcc -fPIC -c /server/hlds_l/nowon.c -o /server/hlds_l/nowon.o && \
    ld -shared -o /server/hlds_l/nowon.so /server/hlds_l/nowon.o && \
    rm -f /server/hlds_l/nowon.c /server/hlds_l/nowon.o

# Modify hlds_run to include LD_PRELOAD
RUN sed -i '/^export /a export LD_PRELOAD="nowon.so"' /server/hlds_l/hlds_run
RUN touch /server/hlds_l/cstrike/listenserver.cfg && \
	touch /server/hlds_l/cstrike/server.cfg && \ 
	sed -i '/^hostname /c\hostname "PHILIPPINES 24/7 CS 1.3"' /server/hlds_l/cstrike/listenserver.cfg && \
    sed -i '/^hostname /c\hostname "PHILIPPINES 24/7 CS 1.3"' /server/hlds_l/cstrike/server.cfg
	
RUN touch /server/hlds_l/cstrk15/listenserver.cfg && \
	touch /server/hlds_l/cstrk15/server.cfg && \ 
	sed -i '/^hostname /c\hostname "PHILIPPINES 24/7 CS 1.5"' /server/hlds_l/cstrk15/listenserver.cfg && \
    sed -i '/^hostname /c\hostname "PHILIPPINES 24/7 CS 1.5"' /server/hlds_l/cstrk15/server.cfg

	
USER hlds

ENV TERM xterm

ENTRYPOINT ["./hlds_run"]
CMD ["-game", "cstrike", "+map", "de_dust2", "+maxplayers", "16"]
