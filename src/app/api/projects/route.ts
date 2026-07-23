import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const status = searchParams.get('status');
    const search = searchParams.get('search');

    const where: Record<string, unknown> = {};
    if (status && status !== 'all') where.status = status;
    if (search) {
      where.OR = [
        { clientName: { contains: search } },
        { clientCompany: { contains: search } },
        { projectNumber: { contains: search } },
        { clientEmail: { contains: search } },
      ];
    }

    const projects = await db.project.findMany({
      where,
      include: {
        services: true,
        agreements: { include: { template: { select: { name: true } } } },
        questionnaires: {
          include: {
            template: { select: { name: true } },
            _count: { select: { responses: true } },
          },
        },
        documents: true,
      },
      orderBy: { createdAt: 'desc' },
    });

    return NextResponse.json({ projects });
  } catch (error) {
    console.error('Error fetching projects:', error);
    return NextResponse.json({ error: 'Failed to fetch projects' }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { clientName, clientEmail, clientPhone, clientCompany, woocommerceOrderId, serviceIds, notes } = body;

    // Generate project number
    const lastProject = await db.project.findFirst({ orderBy: { createdAt: 'desc' }, select: { projectNumber: true } });
    let nextNum = 1;
    if (lastProject?.projectNumber) {
      const match = lastProject.projectNumber.match(/BV-\d{4}-(\d+)/);
      if (match) nextNum = parseInt(match[1]) + 1;
    }
    const year = new Date().getFullYear();
    const projectNumber = `BV-${year}-${String(nextNum).padStart(6, '0')}`;

    const project = await db.project.create({
      data: {
        projectNumber,
        clientName: clientName || '',
        clientEmail: clientEmail || '',
        clientPhone: clientPhone || '',
        clientCompany: clientCompany || '',
        woocommerceOrderId: woocommerceOrderId || '',
        notes: notes || '',
        services: serviceIds ? {
          create: (serviceIds as string[]).map(serviceId => ({ serviceId })),
        } : undefined,
      },
      include: {
        services: true,
        agreements: true,
        questionnaires: { include: { template: { select: { name: true } } } },
        documents: true,
      },
    });

    return NextResponse.json({ project }, { status: 201 });
  } catch (error) {
    console.error('Error creating project:', error);
    return NextResponse.json({ error: 'Failed to create project' }, { status: 500 });
  }
}