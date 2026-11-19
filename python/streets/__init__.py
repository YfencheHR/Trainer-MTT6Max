"""Servicios de decisión por calle. Una calle sin servicio registrada aquí no la decide la IA."""

from streets.flop import FlopService

SERVICES = {service.street: service for service in (FlopService(),)}


def available():
    return sorted(SERVICES)


def service_for(street):
    return SERVICES.get(street)
