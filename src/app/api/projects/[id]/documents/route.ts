import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';
import { writeFile, mkdir } from 'fs/promises';
import { existsSync } from 'fs';
import path from 'path';
import crypto from 'crypto';

const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
const MAX_SIZE = 10 * 1024 * 1024; // 10MB

export async function GET(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    const documents = await db.projectDocument.findMany({
      where: { projectId: id },
      orderBy: { createdAt: 'desc' },
    });
    return NextResponse.json({ documents });
  } catch (error) {
    console.error('Error fetching documents:', error);
    return NextResponse.json({ error: 'Failed to fetch documents' }, { status: 500 });
  }
}

export async function POST(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    const formData = await request.formData();
    const file = formData.get('file') as File | null;
    const name = formData.get('name') as string || file?.name || 'Document';
    const category = formData.get('category') as string || 'other';

    if (!file) {
      return NextResponse.json({ error: 'No file provided' }, { status: 400 });
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
      return NextResponse.json({ error: 'Invalid file type' }, { status: 400 });
    }

    if (file.size > MAX_SIZE) {
      return NextResponse.json({ error: 'File too large. Maximum 10MB' }, { status: 400 });
    }

    const bytes = await file.arrayBuffer();
    const buffer = Buffer.from(bytes);
    const ext = path.extname(file.name) || '.pdf';
    const filename = `${id}-${crypto.randomUUID()}${ext}`;

    const uploadDir = path.join(process.cwd(), 'public', 'uploads', 'documents');
    if (!existsSync(uploadDir)) await mkdir(uploadDir, { recursive: true });

    const filepath = path.join(uploadDir, filename);
    await writeFile(filepath, buffer);

    const document = await db.projectDocument.create({
      data: {
        projectId: id,
        name,
        filename,
        filepath: `/uploads/documents/${filename}`,
        filesize: file.size,
        mimeType: file.type,
        category,
      },
    });

    return NextResponse.json({ document }, { status: 201 });
  } catch (error) {
    console.error('Error uploading document:', error);
    return NextResponse.json({ error: 'Upload failed' }, { status: 500 });
  }
}

export async function DELETE(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id: projectId } = await params;
    const { searchParams } = new URL(request.url);
    const docId = searchParams.get('docId');
    if (!docId) return NextResponse.json({ error: 'Document ID required' }, { status: 400 });

    await db.projectDocument.delete({ where: { id: docId } });
    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Error deleting document:', error);
    return NextResponse.json({ error: 'Failed to delete document' }, { status: 500 });
  }
}