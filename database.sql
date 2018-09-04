CREATE TABLE `challenges` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`description` TEXT NOT NULL,
	`conditions` TEXT NOT NULL,
	`example` TEXT NOT NULL,
	`tests` TEXT NOT NULL,
	`solution` TEXT NOT NULL,
	`status` TINYINT(1) NOT NULL DEFAULT '0',
	`solved` INT(11) NOT NULL DEFAULT '0',
	`current` TINYINT(1) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	UNIQUE KEY `ix_challenges` (`name`, `description`,`tests`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`username` VARCHAR(64) NOT NULL,
	`points` INT(7) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	UNIQUE KEY `ix_users` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

CREATE TABLE `solved`(
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`challengeid` INT(11) NOT NULL DEFAULT '0',
	`username` VARCHAR(64) NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;