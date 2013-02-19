create table foo
(
   id                   int unsigned not null auto_increment,
   a                    int not null,
   b                    int not null,
   c                    varchar(50) not null,
   d                    varchar(50) not null,
   e                    varchar(50) not null,
   primary key (id)
)
engine = innodb;
